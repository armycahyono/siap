<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\LessonSchedule;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TimetableItem;
use App\Models\TimetableUpload;
use App\Models\User;
use App\Services\TimetableParserService;
use App\Services\TimetableSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TimetableUploadController extends Controller
{
    protected function authorizeAccess(): void
    {
        abort_unless(
            auth()->check() && auth()->user()->hasRole(['super_admin', 'waka_kurikulum', 'kepala_sekolah', 'operator_kurikulum']),
            403,
            'Hanya Waka Kurikulum, Kepala Sekolah, dan Operator Kurikulum yang berhak mengakses arsip jadwal.'
        );
    }

    public function index()
    {
        $this->authorizeAccess();

        $uploads = TimetableUpload::with('uploader')
            ->withCount([
                'items as total_translation_items',
                'items as invalid_translation_items' => fn ($q) => $q->where('is_valid', false),
            ])
            ->latest()
            ->paginate(10);

        $setting = SchoolSetting::getSetting();

        return view('kurikulum.timetables.index', compact('uploads', 'setting'));
    }

    public function store(Request $request, TimetableParserService $parserService)
    {
        $this->authorizeAccess();

        $request->validate([
            'pdf_file' => 'required|mimes:pdf|max:15360',
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'academic_year' => 'nullable|string',
            'semester' => 'nullable|string',
        ]);

        $setting = SchoolSetting::getSetting();
        $academicYear = $request->input('academic_year') ?: ($setting->academic_year ?? '2026/2027');
        $semester = $request->input('semester') ?: ($setting->semester ?? 'Ganjil');

        $file = $request->file('pdf_file');
        $path = $file->store('timetables', 'public');

        $upload = TimetableUpload::create([
            'academic_year' => $academicYear,
            'semester' => $semester,
            'title' => $request->input('title'),
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
            'notes' => $request->input('notes'),
            'total_classes' => 0,
            'total_lessons' => 0,
            'is_active' => false,
        ]);

        try {
            $parsedData = $parserService->parsePdf(storage_path('app/public/'.$path));
            if (! empty($parsedData)) {
                $result = TimetableSyncService::populateTranslationItems($parsedData, $upload);

                return redirect()->route('kurikulum.timetables.translation', $upload)->with('success', "File jadwal PDF berhasil diupload dan diterjemahkan ({$result['total_lessons']} slot, {$result['total_classes']} kelas). Silakan review & koreksi hasil terjemahan sebelum diterapkan ke jadwal aktif.");
            }
        } catch (Throwable $e) {
            // Keep uploaded file in archive
        }

        return redirect()->route('kurikulum.timetables.translation', $upload)->with('warning', 'File PDF tersimpan, namun parsing otomatis memerlukan review manual.');
    }

    public function translation(TimetableUpload $upload, Request $request)
    {
        $this->authorizeAccess();

        // If items are empty but the physical PDF exists, parse it now
        if ($upload->items()->count() === 0 && Storage::disk('public')->exists($upload->file_path)) {
            try {
                $parserService = new TimetableParserService;
                $parsedData = $parserService->parsePdf(storage_path('app/public/'.$upload->file_path));
                if (! empty($parsedData)) {
                    TimetableSyncService::populateTranslationItems($parsedData, $upload);
                }
            } catch (Throwable $e) {
                Log::warning('Failed parsing timetable PDF during translation view: '.$e->getMessage());
            }
        }

        $query = $upload->items()->with(['classroom', 'subject', 'teacher']);

        $selectedClass = $request->input('class_name');
        if ($selectedClass) {
            $query->where('class_name', $selectedClass);
        }

        $selectedDay = $request->input('day');
        if ($selectedDay) {
            $query->where('day', $selectedDay);
        }

        $selectedStatus = $request->input('status');
        if ($selectedStatus === 'invalid') {
            $query->where('is_valid', false);
        } elseif ($selectedStatus === 'valid') {
            $query->where('is_valid', true);
        }

        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject_code', 'like', "%{$search}%")
                    ->orWhere('teacher_nickname', 'like', "%{$search}%")
                    ->orWhere('class_name', 'like', "%{$search}%")
                    ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('teacher', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            });
        }

        $items = $query->orderByRaw("FIELD(day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
            ->orderBy('class_name')
            ->orderBy('start_period')
            ->paginate(50)
            ->withQueryString();

        $classes = $upload->items()->distinct()->orderBy('class_name')->pluck('class_name');
        $totalItems = $upload->items()->count();
        $validItems = $upload->items()->where('is_valid', true)->count();
        $invalidItems = $upload->items()->where('is_valid', false)->count();

        // Master data for correction modals
        $classrooms = Classroom::where('academic_year', $upload->academic_year)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        if ($classrooms->isEmpty()) {
            $classrooms = Classroom::orderBy('name')->get();
        }

        $subjects = Subject::orderBy('name')->get();
        $teachers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['Guru', 'Waka Kurikulum', 'Kepala Sekolah', 'Supervisor']))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('kurikulum.timetables.translation', compact(
            'upload',
            'items',
            'classes',
            'totalItems',
            'validItems',
            'invalidItems',
            'classrooms',
            'subjects',
            'teachers',
            'selectedClass',
            'selectedDay',
            'selectedStatus',
            'search'
        ));
    }

    public function updateItem(Request $request, TimetableUpload $upload, TimetableItem $item)
    {
        $this->authorizeAccess();

        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
            'start_period' => 'required|integer|min:1|max:10',
            'end_period' => 'required|integer|min:1|max:10|gte:start_period',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:users,id',
        ]);

        $classroom = Classroom::find($request->classroom_id);
        $subject = Subject::find($request->subject_id);
        $teacher = User::find($request->teacher_id);
        $times = LessonSchedule::getTimeForPeriod((int) $request->start_period, (int) $request->end_period);

        $item->update([
            'classroom_id' => $classroom->id,
            'class_name' => $classroom->name,
            'day' => $request->day,
            'start_period' => $request->start_period,
            'end_period' => $request->end_period,
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
            'subject_id' => $subject->id,
            'subject_code' => $subject->code,
            'teacher_id' => $teacher->id,
            'teacher_nickname' => $teacher->name,
            'is_valid' => true,
            'status_message' => null,
        ]);

        return back()->with('success', "Slot jadwal {$classroom->name} ({$item->day} Jam {$item->start_period}-{$item->end_period}) berhasil diperbarui.");
    }

    public function storeItem(Request $request, TimetableUpload $upload)
    {
        $this->authorizeAccess();

        $request->validate([
            'classroom_id' => 'required|exists:classrooms,id',
            'day' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
            'start_period' => 'required|integer|min:1|max:10',
            'end_period' => 'required|integer|min:1|max:10|gte:start_period',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:users,id',
        ]);

        $classroom = Classroom::find($request->classroom_id);
        $subject = Subject::find($request->subject_id);
        $teacher = User::find($request->teacher_id);
        $times = LessonSchedule::getTimeForPeriod((int) $request->start_period, (int) $request->end_period);

        TimetableItem::create([
            'timetable_upload_id' => $upload->id,
            'classroom_id' => $classroom->id,
            'class_name' => $classroom->name,
            'day' => $request->day,
            'start_period' => $request->start_period,
            'end_period' => $request->end_period,
            'start_time' => $times['start_time'],
            'end_time' => $times['end_time'],
            'subject_id' => $subject->id,
            'subject_code' => $subject->code,
            'teacher_id' => $teacher->id,
            'teacher_nickname' => $teacher->name,
            'is_valid' => true,
            'status_message' => null,
        ]);

        $upload->update([
            'total_lessons' => $upload->items()->count(),
            'total_classes' => $upload->items()->distinct()->count('class_name'),
        ]);

        return back()->with('success', "Slot jadwal baru berhasil ditambahkan ke hasil terjemahan {$classroom->name}.");
    }

    public function destroyItem(TimetableUpload $upload, TimetableItem $item)
    {
        $this->authorizeAccess();

        $item->delete();

        $upload->update([
            'total_lessons' => $upload->items()->count(),
            'total_classes' => $upload->items()->distinct()->count('class_name'),
        ]);

        return back()->with('success', 'Slot terjemahan berhasil dihapus.');
    }

    public function reparse(TimetableUpload $upload, TimetableParserService $parserService)
    {
        $this->authorizeAccess();

        if (! Storage::disk('public')->exists($upload->file_path)) {
            return back()->with('error', 'File PDF asli tidak ditemukan di server.');
        }

        try {
            $parsedData = $parserService->parsePdf(storage_path('app/public/'.$upload->file_path));
            if (! empty($parsedData)) {
                $result = TimetableSyncService::populateTranslationItems($parsedData, $upload);

                return back()->with('success', "Proses terjemahan ulang PDF selesai: {$result['total_lessons']} slot terjemahan ({$result['total_valid']} valid, {$result['total_invalid']} perlu koreksi).");
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menerjemahkan ulang file PDF: '.$e->getMessage());
        }

        return back()->with('warning', 'Parsing PDF tidak menghasilkan data slot jadwal.');
    }

    public function applyTranslation(TimetableUpload $upload)
    {
        $this->authorizeAccess();

        $result = TimetableSyncService::applyTranslationToLive($upload);

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Gagal menerapkan hasil terjemahan.');
        }

        return redirect()->route('kurikulum.schedules.index', [
            'academic_year' => $upload->academic_year,
            'semester' => $upload->semester,
        ])->with('success', "Hasil terjemahan jadwal '{$upload->title}' berhasil diterapkan menjadi Jadwal Pelajaran Aktif ({$result['total_classes']} rombel, {$result['total_lessons']} slot jadwal).");
    }

    public function download(TimetableUpload $upload)
    {
        $this->authorizeAccess();

        if (! Storage::disk('public')->exists($upload->file_path)) {
            return back()->with('error', 'File PDF tidak ditemukan di server.');
        }

        return Storage::disk('public')->download($upload->file_path, $upload->original_filename);
    }

    public function preview(TimetableUpload $upload)
    {
        $this->authorizeAccess();

        if (! Storage::disk('public')->exists($upload->file_path)) {
            abort(404, 'File PDF tidak ditemukan di server.');
        }

        $filePath = Storage::disk('public')->path($upload->file_path);

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$upload->original_filename.'"',
        ]);
    }

    public function reapply(TimetableUpload $upload)
    {
        $this->authorizeAccess();

        // If items exist, apply them directly
        if ($upload->items()->where('is_valid', true)->count() > 0) {
            return $this->applyTranslation($upload);
        }

        TimetableUpload::where('academic_year', $upload->academic_year)
            ->where('semester', $upload->semester)
            ->update(['is_active' => false]);

        $upload->update(['is_active' => true]);

        return redirect()->route('kurikulum.schedules.index', [
            'academic_year' => $upload->academic_year,
            'semester' => $upload->semester,
        ])->with('success', "Arsip Jadwal '{$upload->title}' telah diaktifkan kembali.");
    }

    public function destroy(TimetableUpload $upload)
    {
        $this->authorizeAccess();

        if ($upload->file_path && Storage::disk('public')->exists($upload->file_path)) {
            Storage::disk('public')->delete($upload->file_path);
        }

        $upload->items()->delete();
        $upload->delete();

        return redirect()->route('kurikulum.timetables.index')->with('success', 'Arsip jadwal PDF dan data terjemahannya berhasil dihapus.');
    }
}
