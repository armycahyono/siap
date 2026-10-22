import sys
import json
import os
import pypdf

def parse_asc_pdf(pdf_path):
    if not os.path.exists(pdf_path):
        raise FileNotFoundError(f"File not found: {pdf_path}")

    reader = pypdf.PdfReader(pdf_path)

    # Standard period column coordinates in aSc Timetables landscape format
    x_start_map = [
        (93.6, 1), (154.9, 2), (216.3, 3),
        (339.0, 4), (400.4, 5), (461.7, 6), (523.1, 7),
        (645.8, 8), (707.1, 9), (768.5, 10),
    ]
    x_end_map = [
        (154.9, 1), (216.3, 2), (277.7, 3),
        (400.4, 4), (461.7, 5), (523.1, 6), (584.4, 7),
        (707.1, 8), (768.5, 9), (829.8, 10),
    ]

    def map_start_p(x):
        return min(x_start_map, key=lambda item: abs(item[0] - x))[1]

    def map_end_p(x):
        return min(x_end_map, key=lambda item: abs(item[0] - x))[1]

    day_map = [
        (485.2, 'Senin'),
        (394.2, 'Selasa'),
        (303.1, 'Rabu'),
        (212.0, 'Kamis'),
        (120.9, 'Jumat'),
    ]

    def map_day(ymax):
        return min(day_map, key=lambda item: abs(item[0] - ymax))[1]

    alias_map = {
        'X AK': 'X AKL 1',
        'X TKJ': 'X TKJ 1',
        'XI AK': 'XI AKL 1',
        'XI TKJ': 'XI TKJ 1',
        'XII AK': 'XII AKL 1',
    }

    known_teachers = {
        'ADI NUR', 'AFIF', 'AGUNG', 'ALIYA', 'AYU', 'BAYU', 'BUDI',
        'CANDRA', 'DEVI', 'DIAH', 'DITA', 'EFENDI', 'EKO', 'ELLA',
        'ILHAM ALI', 'INDRA', 'JUMARI', 'KARNI', 'KRIS', 'LEO', 'LUTFI',
        'MAKMUN', 'MIFTAH', 'MUNIF', 'NURMA', 'OKKY', 'PUNGKAS', 'QURNIA',
        'RANI', 'RENO', 'RIMA', 'RISNA', 'ROFIK', 'ROKHIM', 'RUDI',
        'SUHADAK', 'SUPRI', 'TETUKO', 'UTORO', 'WARNI', 'YOGA'
    }

    results = {}

    for p_idx, page in enumerate(reader.pages):
        tokens = []
        page.extract_text(visitor_text=lambda t, cm, tm, f, s: tokens.append((round(tm[4], 1), round(tm[5], 1), t.strip())) if t.strip() else None)
        
        # Classroom name is at the top of the page (y around 540-570)
        class_tokens = [t[2] for t in tokens if 540 < t[1] < 570]
        class_raw = class_tokens[0] if class_tokens else f'Page_{p_idx}'
        class_name = alias_map.get(class_raw, class_raw)
        
        # Determine department code for fallback on KONSJUR / MAPIL
        dept = 'TKR'
        if 'AK' in class_name or 'AKL' in class_name:
            dept = 'AK'
        elif 'TKJ' in class_name:
            dept = 'TKJ'
            
        content = page.get_contents()
        current_box_pts = []
        boxes = []
        for operands, operator in content.operations:
            if operator == b'm':
                current_box_pts = [(float(operands[0]), float(operands[1]))]
            elif operator == b'l':
                current_box_pts.append((float(operands[0]), float(operands[1])))
            elif operator in [b'f', b'S', b's', b'B', b'b']:
                if len(current_box_pts) >= 4:
                    xs = [p[0] for p in current_box_pts]
                    ys = [p[1] for p in current_box_pts]
                    xmin, xmax, ymin, ymax = min(xs), max(xs), min(ys), max(ys)
                    w = xmax - xmin
                    h = ymax - ymin
                    if 80 < h < 100 and w > 40:
                        boxes.append((round(xmin, 1), round(xmax, 1), round(ymin, 1), round(ymax, 1)))

        boxes = sorted(list(set(boxes)), key=lambda b: (-b[2], b[0]))
        
        class_schedule = {'Senin': [], 'Selasa': [], 'Rabu': [], 'Kamis': [], 'Jumat': []}
        
        for xmin, xmax, ymin, ymax in boxes:
            day = map_day(ymax)
            start_p = map_start_p(xmin)
            end_p = map_end_p(xmax)
            
            box_toks = [
                t for t in tokens
                if xmin - 2 <= t[0] <= xmax + 2
                and ymin + 5 <= t[1] <= ymax - 5
                and 'ISTIRAHAT' not in t[2]
                and '-' not in t[2]
                and ':' not in t[2]
            ]
            box_toks.sort(key=lambda t: -t[1])
            
            found_teacher = None
            remaining_toks = []
            for t in box_toks:
                if not found_teacher and t[2] in known_teachers:
                    found_teacher = t[2]
                else:
                    remaining_toks.append(t[2])
                    
            if not found_teacher:
                if box_toks:
                    found_teacher = box_toks[-1][2]
                    remaining_toks = [t[2] for t in box_toks[:-1]]
                else:
                    found_teacher = 'UNKNOWN'
                    
            subj_str = ' '.join(remaining_toks) if remaining_toks else 'UNKNOWN'
            
            # Normalize composite subject codes
            if 'KONS' in subj_str and 'JUR' in subj_str:
                subj_str = f'KONSJUR {dept}'
            elif subj_str in ['KONS', 'KONSJUR']:
                subj_str = f'KONSJUR {dept}'
            elif subj_str == 'MAPIL':
                subj_str = f'MAPIL {dept}'
                
            class_schedule[day].append({
                'subject': subj_str,
                'teacher': found_teacher,
                'start': start_p,
                'end': end_p,
                'hours': end_p - start_p + 1,
            })
        
        results[class_name] = class_schedule

    return results

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'PDF file path required'}))
        sys.exit(1)
        
    pdf_path = sys.argv[1]
    try:
        data = parse_asc_pdf(pdf_path)
        print(json.dumps(data))
    except Exception as e:
        print(json.dumps({'error': str(e)}))
        sys.exit(1)
