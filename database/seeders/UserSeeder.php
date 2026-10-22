<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $wakaRole = Role::where('slug', 'waka_kurikulum')->first();
        $supervisorRole = Role::where('slug', 'supervisor')->first();
        $kepsekRole = Role::where('slug', 'kepala_sekolah')->first();
        $guruRole = Role::where('slug', 'guru')->first();

        // 1. Super Admin Akun
        User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Administrator Sistem',
                'email' => 'admin@smkpgrikras.sch.id',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
                'phone' => '081234567890',
                'position' => 'Administrator Sistem',
                'is_active' => true,
            ]
        );

        // 2. Waka Kurikulum (Akun Standar untuk Login Waka)
        User::firstOrCreate(
            ['username' => 'waka'],
            [
                'name' => 'ROKHIM, S.Pd.',
                'email' => 'waka.kurikulum@smkpgrikras.sch.id',
                'password' => Hash::make('password'),
                'role_id' => $wakaRole->id,
                'position' => 'Waka Kurikulum',
                'phone' => '083116101856',
                'is_active' => true,
            ]
        );

        // 3. Supervisor Standar
        User::firstOrCreate(
            ['username' => 'supervisor'],
            [
                'name' => 'SUPRIYANTO, ST',
                'email' => 'supervisor@smkpgrikras.sch.id',
                'password' => Hash::make('password'),
                'role_id' => $supervisorRole->id,
                'position' => 'Guru / Koordinator Supervisor',
                'phone' => '085735876014',
                'is_active' => true,
            ]
        );

        // Data 54 Guru & GTK SMK PGRI KRAS
        $rawGtk = [
            [1, '4 1989 02', '48902', '02', 'Drs. NONO SUHARYONO', 'Tulungagung, 04 Agustus 1963', '1963', '3504030408630002', '085730160390', 'GTT', 'L', 'Bendahara Sekolah', '1989', 'IKIP Malang', 'KIMIA', 'Sarjana', 'Pendidikan KIMIA', '1989', 'Dsn. Boro Rt. 001 Rw. 006 Ds. Boro Kec. Kedungwaru Kab. Tulungagung Prov. Jawa Timur 66229', 'Kab. Tulungagung', '66229'],
            [2, '4 1989 03', '48903', '03', 'Drs. SUKARNI', 'Kediri, 18 Maret 1962', '1962', '3506031803620003', '085733021209', 'GTT', 'L', 'Guru', '1989', 'IKIP PGRI Kediri', 'PKn, IPS', 'Sarjana', 'PMP dan Kewargaan Negara', '1990', 'Dsn. Bendosari Rt. 005 Rw. 002 Ds. Bendosari Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [3, '4 1989 04', '48904', '04', 'SUWARNI, S.PdI', 'Kediri, 08 Juli 1962', '1962', '3506230807620006', '085645089855', 'GTY', 'L', 'Guru', '1989', 'Institut Agama Islam Tribakti KEDIRI', 'AGAMA', 'Sarjana', 'PAI', '2008', 'Dsn. Sumberwangi Rt. 003 Rw. 009 Ds. Jabang Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [4, '4 1995 01', '49501', '01', 'CINTA IKA WINDAHLIA', 'Kediri, 19 Oktober 1973', '1973', '3506035910730002', '085755579711', 'PTY', 'P', 'Ka. Tenaga Administrasi', '1995', '-', '-', '-', '-', '1993', 'Dsn. Ngrombeh Rt. 002 Rw. 002 Ds. Jambean Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [5, '4 1995 02', '49502', '02', 'ENDANG PATMOWATI, S.Pd', 'Kediri, 23 Januari 1970', '1970', '3506056301700001', '085652153203', 'GTY', 'P', 'Kepala Sekolah', '1995', 'IKIP PGRI Kediri', 'BAHASA INDONESIA', 'Sarjana', 'Pendidikan Bahasa dan Sastra Indonesia', '1995', 'Dsn. Nglarangan Rt. 001 Rw. 003 Ds. Selosari Kec. Kandat Kab. Kediri Prov. Jawa Timur 64173', 'Kab. Kediri', '64173'],
            [6, '4 1999 01', '49901', '01', 'JUMARI, S.Pd.', 'Kediri, 04 April 1970', '1970', '3506030404700002', '085607735795', 'GTY', 'L', 'Guru', '1999', 'IKIP SURABAYA', 'PROD. TKR', 'Sarjana', 'Pendidikan Teknik Mesin', '1994', 'Dsn. Dawuhan Rt. 003 Rw. 008 Ds. Mojosari Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [7, '4 2000 01', '40001', '01', 'SAIFUDIN', 'Kediri, 11 Nopember 1975', '1975', '3506041111750001', '085336810843', 'PTT', 'L', 'Penjaga', '2000', '-', '-', '-', '-', '1987', 'Dsn. Kras Rt. 003 Rw. 003 Ds. Kras Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [8, '4 2005 01', '40501', '01', 'AHMAD RIFAI', 'Kediri, 06 Nopember 1985', '1985', '3506030611850001', '085335848208', 'PTY', 'L', 'Staf Tenaga Administrasi', '2005', '-', '-', '-', '-', '2005', 'Dsn. Sumberkepuh Rt. 021 Rw. 005 Ds. Butuh Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [9, '4 2006 01', '40601', '01', 'SUPRIYANTO, ST', 'Tulungagung, 24 November 1970', '1970', '3504012411700004', '085735876014', 'GTY', 'L', 'Guru', '2006', 'Institut Teknologi Pembangunan SURABAYA', 'PROD. TKR', 'Sarjana', 'Pendidikan Teknik Mesin', '2003', 'Dsn. Bendil Rt. 002 Rw. 003 Ds. Panggungrejo Kec. Tulungagung Kab. Tulungagung Prov. Jawa Timur 66214', 'Kab. Tulungagung', '66214'],
            [10, '4 2007 01', '40701', '01', 'UTORO, S.Fil., S.Pd', 'Kediri, 11 Maret 1973', '1973', '3506031103730002', '085655570195', 'GTY', 'L', 'Guru', '2007', 'Universitas Nusantara PGRI KEDIRI', 'KKPI', 'Sarjana', 'Filsafat, PKN', '2010', 'Dsn. Ngasem Rt. 002 Rw. 002 Ds. Rejomulyo Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [11, '4 2008 01', '40801', '01', 'ENDANG PUNGKASWATI, S.Pd', 'Kediri, 28 Agustus 1969', '1969', '3506046808690004', '081217435282', 'GTY', 'P', 'Guru', '2008', 'IKIP PGRI Kediri', 'MATEMATIKA', 'Sarjana', 'Pendidikan Matematika', '1994', 'Dsn. Pagak Rt. 002 Rw. 002 Ds. Banggle Kec. Ngadiluwih Kab. Kediri Prov. Jawa Timur 64171', 'Kab. Kediri', '64171'],
            [12, '4 2008 02', '40802', '02', 'NURMA FAUZIAH, S.Pd.', 'Kediri, 23 April 1983', '1983', '3506056304830001', '082218846223', 'GTY', 'P', 'Guru', '2008', 'Universitas Negeri SURABAYA', 'FISIKA, KIMIA', 'Sarjana', 'Pendidikan KIMIA', '2005', 'Jl. Raya Kandat Rt. 001 Rw. 002 Ds. Kandat Kec. Kandat Kab. Kediri Prov. Jawa Timur 64173', 'Kab. Kediri', '64173'],
            [13, '4 2010 02', '41002', '02', 'TOMAS HADI WIJAYA', 'Kediri, 09 Maret 1991', '1991', '3506030903910002', '085608507563', 'PTY', 'L', 'Staf Tenaga Administrasi', '2010', '-', '-', '-', '-', '2010', 'Dsn. Boro Rt. 001 Rw. 003 Ds. Banjaranyar Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [14, '4 2011 01', '41101', '01', 'ADI NURANTO, S.Pd.', 'Kediri, 19 Pebruari 1974', '1974', '3506041902740001', '081336750275', 'GTT', 'L', 'Guru', '2011', 'IKIP PGRI Kediri', 'Pendidikan Bahasa dan Sastra Indonesia', 'Sarjana', 'Pendidikan Bahasa dan Sastra Indonesia', '1999', 'Dsn. Setono Rt. 002 Rw. 005 Ds. Tales Kec. Ngadiluwih Kab. Kediri Prov. Jawa Timur 64171', 'Kab. Kediri', '64171'],
            [15, '4 2011 03', '41103', '03', 'SUHADAK, ST', 'Kediri, 18 Juni 1984', '1984', '3506041806840001', '085887281994', 'GTY', 'L', 'Guru', '2011', 'Univ. 17 Agustus 1945 SURABAYA', 'PROD. TKR', 'Sarjana', 'Teknik Industri', '2010', 'Dsn. Jatirejo Rt. 001 Rw. 001 Ds. Kanigoro Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [16, '4 2013 01', '41301', '01', 'ARMY WIDHI CAHYONO, S.Pt.', 'Kediri, 12 Januari 1984', '1984', '3506033103800001', '085855559905', 'GTY', 'L', 'Guru', '2013', 'Universitas Muhammadiyah Malang', 'KKPI, PROD. TKJ', 'Sarjana', 'Peternakan', '2003', 'Dsn. Boro Rt. 003 Rw. 004 Ds. Banjaranyar Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [17, '4 2014 02', '41402', '02', 'EFENDI SAPUTRA', 'Kediri, 02 Desember 1992', '1992', '3506030212920002', '085852444439', 'PTY', 'L', 'Guru', '2014', 'Universitas Terbuka', 'Pendidikan Bahasa dan Sastra Indonesia', 'Sarjana', 'Pendidikan Bahasa dan Sastra Indonesia', '2025', 'Dsn. Kras Rt. 004 Rw. 004 Ds. Kras Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [18, '4 2014 04', '41404', '04', 'CANDRA TRI SETYONO, S.Pd.', 'Kediri, 21 September 1989', '1989', '3506142109890003', '082324558035', 'GTY', 'L', 'Guru', '2014', 'Universitas Nusantara PGRI KEDIRI', 'PENJASKES', 'Sarjana', 'Pendidikan Jasmani, Kesehatan dan Rekreasi', '2011', 'Jl. Arjuno Rt. 001 Rw. 006 Ds. Pesing Kec. Purwoasri Kab. Kediri Prov. Jawa Timur 64154', 'Kab. Kediri', '64154'],
            [19, '4 2015 02', '41502', '02', 'RASTA SUDITA PURA', 'Kediri, 31 Juli 1994', '1994', '3506033107940002', '085859784662', 'PTY', 'L', 'Toolman TKJ dan AK', '2015', '-', '-', '-', '-', '2014', 'Dsn. Jagung Rt. 001 Rw. 008 Ds. Kras Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [20, '4 2015 03', '41503', '03', 'FERRY FERDIAN HAYON', 'Kediri, 23 Januari 1993', '1993', '3506032301930001', '085785119736', 'PTY', 'L', 'Satpam', '2015', '-', '-', '-', '-', '2014', 'Dsn. Ngasem Rt. 001 Rw. 002 Ds. Rejomulyo Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [21, '4 2017 01', '41701', '01', 'QURNIA NI\'MATUL ULFAH, S.Pd', 'Kediri, 21 Juni 1992', '1992', '3506236106920001', '085735070210', 'GTY', 'P', 'Guru', '2017', 'Universitas Negeri Malang', 'Pendidikan Fisika', 'Sarjana', 'Fisika', '2014', 'Dsn. Jagung Rt. 003 Rw. 008 Ds. Kras Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [22, '4 2017 02', '41702', '02', 'AYU DWI WAHYU SAPUTRI, S.Pd', 'Kediri, 20 September 1990', '1990', '3571026009900003', '085707166614', 'GTY', 'P', 'Guru', '2017', 'Universitas Negeri Malang', 'Teknologi Pendidikan', 'Sarjana', 'Teknologi Pendidikan', '2013', 'Jl. Melati II No. 16 Rt. 008 Rw. 008 Ds. Ngronggo Kec. Kota Kediri Kota Kediri Prov. Jawa Timur 64127', 'Kota Kediri', '64127'],
            [23, '4 2017 05', '41705', '05', 'DITA MAGHFIRATUL MAS\'RURIN, S.Pd.', 'Kediri, 07 Oktober 1994', '1994', '3506034710940001', '087850146117', 'GTY', 'P', 'Guru', '2017', 'Universitas Nusantara PGRI KEDIRI', 'Pendidikan Ekonomi', 'Sarjana', 'Pendidikan Ekonomi', '2017', 'Dsn. Kromasan Rt. 018 Rw. 006 Ds. Bendosari Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [24, '4 2017 07', '41707', '07', 'YOGA ANGGORINA KUKUH KURNIAWAN, S.Pd.', 'Kediri, 03 Nopember 1992', '1992', '3506030311920002', '085724370492', 'GTY', 'L', 'Guru', '2017', 'Universitas Negeri SURABAYA', 'Pendidikan Bahasa dan Sastra Jawa', 'Sarjana', 'Pendidikan Bahasa dan Sastra Jawa', '2016', 'Dsn. Sumberdadi Rt. 012 Rw. 003 Ds. Butuh Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [25, '4 2017 08', '41708', '08', 'AGUNG PRASETYO, S.Pd.', 'Tanjung, 16 Maret 1994', '1994', '3506231603940002', '082232196595', 'GTY', 'L', 'Guru', '2017', 'Universitas Nusantara PGRI KEDIRI', 'Pendidikan Bahasa dan Sastra Indonesia', 'Sarjana', 'Pendidikan Bahasa dan Sastra Indonesia', '2017', 'Jl. Sultan Agung GG Pasar No. 09 Rt. 005 Rw. 003 Ds. Sambi Kec. Ringinrejo  Kab. Kediri Prov. Jawa Timur 64176', 'Kab. Kediri', '64176'],
            [26, '4 2017 09', '41709', '09', 'INDRA YUSTIAWAN, M.Pd.', 'Kediri, 01 Desember 1993', '1993', '3506230112930002', '085736453252', 'GTY', 'L', 'Guru', '2017', 'IAIN Tulungagung', 'Tadris Bahasa Inggris', 'Magister Pendidikan', 'Tadris Bahasa Inggris', '2021', 'Dsn. Dedehan Rt. 015 Rw. 006 Ds. Jemekan Kec. Ringinrejo Kab. Kediri Prov. Jawa Timur 64176', 'Kab. Kediri', '64176'],
            [27, '4 2017 11', '41711', '11', 'ILHAM BUDI SANTOSO, M.Pd.', 'Kediri, 06 Juli 1995', '1995', '3506030607950003', '085746883135', 'GTY', 'L', 'Guru', '2017', 'Universitas Islam Negeri Tulungagung', 'Tadris Matematika', 'Magister Pendidikan', 'Tadris Matematika', '2023', 'Dsn. Krandang Rt. 001 Rw. 002 Ds. Krandang Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [28, '4 2018 03', '41803', '03', 'ROKHIM, S.Pd.', 'Kediri, 01 Maret 1994', '1994', '3506030103940003', '083116101856', 'GTY', 'L', 'Guru', '2018', 'STKIP PGRI Tulungagung', 'Pendidikan Ekonomi', 'Sarjana', 'Pendidikan Ekonomi', '2017', 'Dsn. Pagak Rt. 002 Rw. 002 Ds. Banggle Kec. Ngadiluwih Kab. Kediri Prov. Jawa Timur 64171', 'Kab. Kediri', '64171'],
            [29, '4 2019 04', '41904', '04', 'RISNA HILALIYAH, S.Pd', 'Lamongan, 16 Oktober 1986', '1986', '3524145610860001', '08563781561', 'GTY', 'P', 'Guru', '2019', 'STKIP PGRI Tulungagung', 'Pendidikan Bahasa Inggris', 'Sarjana', 'Pendidikan Bahasa Inggris', '2011', 'Dsn. Grenjeng Rt. 004 Rw. 002 Ds. Tulungrejo Kec. Karangrejo Kab. Tulungagung Prov. Jawa Timur 66253', 'Kab. Tulungagung', '66253'],
            [30, '4 2019 05', '41905', '05', 'LAYLATUL ROCHMAH, S.Pd.I', 'Kediri, 31 Desember 1986', '1986', '3506047112860001', '085708735440', 'GTT', 'P', 'Guru', '2019', 'STAIN Kediri', 'Tadris Bahasa Inggris', 'Sarjana', 'Tadris Bahasa Inggris', '2009', 'Dsn. Karanglo Rt. 001 Rw. 004 Ds. Tales Kec. Ngadiluwih Kab. Kediri Prov. Jawa Timur 64171', 'Kab. Kediri', '64171'],
            [31, '4 2020 01', '42001', '01', 'RENO AULIYA ANGGRAINI, S.Pd.', 'Kediri, 01 Januari 1997', '1997', '3506034101970003', '085646123861', 'GTY', 'P', 'Guru', '2020', 'STKIP PGRI Tulungagung', 'Pendidikan Pancasila dan Kewarganegaraan', 'Sarjana', 'Pendidikan Pancasila dan Kewarganegaraan', '2019', 'Dsn. Karangdoro Rt. 003 Rw. 001 Ds. Padangan Kec. Ngantru Kab. Tulungagung Prov. Jawa Timur 66252', 'Kab. Tulungagung', '66252'],
            [32, '4 2020 05', '42005', '05', 'OKKY WILLYAND SUPRAPTO, S.Kom.', 'Kediri, 16 Desember 1995', '1995', '3506031612950001', '085708211253', 'GTY', 'L', 'Guru', '2020', 'Universitas Nusantara PGRI KEDIRI', 'Teknik Informatika', 'Sarjana', 'Tenik Informatika', '2018', 'Dsn. Setonorejo Rt. 002 Rw. 001 Ds. Setonorejo Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [33, '4 2020 06', '42006', '06', 'ALIYA NUSAIBA, S.Pd.', 'Tulungagung, 20 Nopember 1991', '1991', '3506036011910003', '082141041602', 'GTY', 'P', 'Guru', '2020', 'Universitas Nusantara PGRI KEDIRI', 'Pendidikan Ekonomi Akuntansi', 'Sarjana', 'Pendidikan Ekonomi Akuntansi', '2015', 'Dsn. Krandang Rt. 005 Rw. 002 Ds. Krandang Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [34, '4 2020 07', '42007', '07', 'EKO AQTHOENA ULFA, S.Pd.', 'Kediri, 01 Juni 1995', '1995', '3506230106950002', '085855990698', 'GTY', 'L', 'Guru', '2020', 'Universitas Nusantara PGRI KEDIRI', 'Pendidikan Jasmani, Kesehatan, dan Rekreasi', 'Sarjana', 'Pendidikan Jasmani, Kesehatan dan Rekreasi', '2019', 'Dsn. Jemekan Timur Rt. 009 Rw. 003 Ds. Jemekan Kec. Riginrejo Kab. Kediri Prov. Jawa Timur 64176', 'Kab. Kediri', '64176'],
            [35, '4 2020 09', '42009', '09', 'AFIF BEY RIDHOTULLOH, A.Md.', 'Kediri, 30 Juli 1995', '1995', '3506033007950001', '082334936938', 'PTY', 'L', 'Guru', '2020', 'Politeknik Negeri Malang', 'Teknik Mesin', 'D3 Politeknik', 'Teknik Mesin', '2017', 'Dsn. Setonorejo Rt. 001 Rw. 002 Ds. Setonorejo Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [36, '4 2021 03', '42103', '03', 'AYU RIMA WARDHANI, S.Pd.', 'Kediri, 01 Juni 1990', '1990', '3506054106900003', '081556543220', 'GTY', 'P', 'Guru', '2021', 'Universitas Nusantara PGRI KEDIRI', 'Pendidikan Ekonomi Akuntansi', 'Sarjana', 'Pendidikan Ekonomi Akuntansi', '2013', 'Dsn. Jambean Rt. 002 Rw. 001 Ds. Jambean Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [37, '4 2021 04', '42104', '04', 'BAYU HADI KUNCORO, S.Kom', 'Gresik, 01 Desember 1997', '1997', '3506030112970001', '087866746285', 'GTY', 'L', 'Guru', '2021', 'STMIK YADIKA BANGIL - Pasuruan', 'Teknik Informatika', 'Sarjana', 'Teknik Informatika', '2020', 'Dsn. Cangkring Rt. 001 Rw. 007 Ds. Banjaranyar Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [38, '4 2021 05', '42105', '05', 'MOCHAMAD ISNUN HARTANTO', 'Kediri, 02 Desember 1991', '1991', '3506030212910001', '085706883589', 'PTY', 'L', 'Pesuruh/Wali Kebun', '2021', '-', '-', '-', '-', '2010', 'Dsn. Cakruk Rt. 003 Rw. 001 Ds. Banjaranyar Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [39, '4 2021 06', '42106', '06', 'MIFTAHUR ROHMAH, S.Pd.', 'Kediri, 12 Mei 1993', '1993', '3506035205930002', '085852073648', 'GTY', 'P', 'Guru', '2021', 'Universitas Negeri Malang', 'Pendidikan Kewarganegaraan', 'Sarjana', 'Pendidikan Kewarganegaraan', '2016', 'Dsn. Bendosari Rt. 002 Rw. 001 Ds. Bendosari Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [40, '4 2022 02', '42202', '02', 'MUHAMMAD ROFIK ASHARI, S.Pd.', 'Tulungagung, 12 Januari 1998', '1998', '3504041201980005', '085725344915', 'GTY', 'L', 'Guru', '2022', 'Universitas Negeri Malang', 'Pendidikan Teknik Otomotif', 'Sarjana', 'Teknik Mesin', '2021', 'Dsn. Karangdoro Rt. 003 Rw. 001 Ds. Padangan Kec. Ngantru Kab. Tulungagung Prov. Jawa Timur 66252', 'Kab. Tulungagung', '66252'],
            [41, '4 2022 05', '42205', '05', 'MAHARANI WAHYU ISMAIL, S.Pd', 'Kediri, 25 April 1997', '1997', '3506056504970003', '085749311143', 'GTY', 'P', 'Guru', '2022', 'Universitas Nusantara PGRI KEDIRI', 'Bimbingan dan Konseling', 'Sarjana', 'Bimbingan Dan Konseling', '2021', 'Dsn. Cendonosari Rt. 011 Rw. 004 Ds. Cendono Kec. Kandat Kab. Kediri Prov. Jawa Timur 64173', 'Kab. Kediri', '64173'],
            [42, '4 2022 06', '42206', '06', 'DEVI AJENG AYU WIBOWO, S.Pd.', 'Kediri, 25 Desember 1990', '1990', '3506036512900002', '081252797161', 'GTY', 'P', 'Guru', '2022', 'Universitas Nusantara PGRI KEDIRI', 'Bimbingan dan Konseling', 'Sarjana', 'Bimbingan Dan Konseling', '2013', 'Dsn. Kendal Doyong Rt. 001 Rw. 001 Ds. Banjarejo Kec. Ngadiluwih Kab. Kediri Prov. Jawa Timur 64171', 'Kab. Kediri', '64171'],
            [43, '4 2022 10', '42210', '10', 'MUCHAMMAD ILHAM ALI NURUDDIN, S.T', 'Kediri, 20 April 2000', '2000', '3506252004000003', '0895367148707', 'GTY', 'L', 'Guru', '2022', 'Universitas Nusantara PGRI KEDIRI', 'Teknik Mesin', 'Sarjana', 'Teknik Mesin', '2022', 'Jl. Jayakatwang Rt. 004 Rw. 002 Ds. Ngasem Kec. Ngasem Kab. Kediri Prov. Jawa Timur 64181', 'Kab. Kediri', '64181'],
            [44, '4 2022 11', '42211', '11', 'LEO HERMAWAN SANTOSO, A.Md.T.', 'Kediri, 15 Agustus 1998', '1998', '3506031508980001', '081216635198', 'PTY', 'L', 'Guru', '2022', 'Politeknik Negeri Malang', 'Teknik Mesin', 'D3 Politeknik', 'Teknik Mesin', '2021', 'Dsn. Bulur Rt. 001 Rw. 005 Ds. Rejomulyo Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [45, '4 2022 12', '42212', '12', 'DONI SETIAWAN', 'Kediri, 5 Agustus 1997', '1997', '3506030508970002', '085732954887', 'PTY', 'L', 'Satpam', '2022', '-', '-', '-', '-', '-', 'Dsn. Boro Ds. Banjaranyar Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [46, '4 2024 01', '42401', '01', 'TETUKO CAHYO WICAKSONO, A.Md.', 'Tulungagung, 22 Desember 1999', '1999', '3504012212990001', '081240142121', 'GTT', 'L', 'Guru', '2024', 'Universitas Negeri Malang', 'Teknik Elektro', 'D3 Teknik', 'Teknik Elektro', '2022', 'Jl. Basuki Rahmat 3/37C Rt. 002 Rw. 001 Ds. Kampungdalem Kec. Tulungagung Kab. Tulungagung Prov. Jawa Timur 66214', 'Kab. Tulungagung', '66214'],
            [47, '4 2024 06', '42406', '06', 'MOH. MAKMUN SOLIKHI, S.Pd.', 'Kediri, 11 Agustus 1998', '1998', '3506031108980002', '085730676730', 'GTT', 'L', 'Guru', '2024', 'Universitas Islam Negeri Tulungagung', 'Tadris Matematika', 'Sarjana', 'Tadris Matematika', '2022', 'Dsn. Bulur Rt. 002 Rw. 004 Ds. Rejomulyo Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [48, '4 2024 08', '42408', '08', 'BAGAS NURNIANTO', 'Kediri, 11 Mei 2005', '2005', '3506031105050002', '085607595187', 'PTT', 'L', 'Toolman TKR', '2024', '-', '-', '-', '-', '-', 'Dsn. Menang Rt. 002 Rw. 007 Ds. Kras Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [49, '4 2024 09', '42409', '09', 'DIAH AYU KUMALASARI, S.Pd.', 'Kediri, 10 Juni 1995', '1995', '3506055006950002', '081229500603', 'GTT', 'P', 'Guru', '2024', 'Universitas Negeri SURABAYA', 'Pendidikan Bahasa Inggris', 'Sarjana', 'Pendidikan Bahasa Inggris', '2021', 'Dsn. Ngletih Barat Rt. 003 Rw. 001 Ds. Ngletih Kec. Kandat Kab. Kediri Prov. Jawa Timur 64173', 'Kab. Kediri', '64173'],
            [50, '4 2024 10', '42410', '10', 'MUNIF ARDIANSYAH, S.Pd.', 'Kediri, 13 September 2000', '2000', '3506181309000001', '081999594732', 'GTT', 'L', 'Guru', '2024', 'Universitas Islam Negeri Tulungagung', 'Pendidikan Agama Islam', 'Sarjana', 'Pendidikan Agama Islam', '2023', 'Dsn. Nglarangan Rt. 010 Rw. 004 Ds. Krenceng Kec. Kepung Kab. Kediri Prov. Jawa Timur 64293', 'Kab. Kediri', '64293'],
            [51, '4 2024 13', '42413', '13', 'KRISDIANTORO, S.Pd.', 'Kediri, 19 Desember 1995', '1995', '3506031912950001', '081231766722', 'GTT', 'L', 'Guru', '2024', 'Universitas Nusantara PGRI KEDIRI', 'Pendidikan Sejarah', 'Sarjana', 'Pendidikan Sejarah', '2018', 'Jl. Brawijaya No. 1 Rt. 006 Rw. 002 Dsn. Bendosari Ds. Bendosari Kec. Kras Kab. Kediri Prov. Jawa Timur 64172', 'Kab. Kediri', '64172'],
            [52, '4 2024 14', '42414', '14', 'LUTFI ANDRI SAPUTRO, S.T', 'Kediri, 21 Juni 1999', '1999', '3506232106990001', '081991203094', 'GTT', 'L', 'Guru', '2024', 'Universitas Nusantara PGRI KEDIRI', 'Teknik Mesin', 'Sarjana', 'Teknik Mesin', '2022', 'Dsn. Petung Rt. 003 Rw. 001 Ds. Sambi Kec. Ringinrejo Kab. Kediri Prov. Jawa Timur 64176', 'Kab. Kediri', '64176'],
            [53, '4 2025 01', '42501', '01', 'MOHAMMAD ILHAM ALFARIZKY, S.Pd.', 'Kediri, 01 November 2001', '2001', '3506050111010005', '085935185128', 'GTT', 'L', 'Guru', '2025', 'Institut Agama Islam Negeri Kediri', 'Tarbiyah', 'Sarjana', 'Pendidikan Agama Islam', '2024', 'Dsn. Pule Utara Rt. 003 Rw. 006 Ds. Pule Kec. Kandat Kab. Kediri Prov. Jawa Timur 64173', 'Kab. Kediri', '64173'],
            [54, '4 2025 04', '42504', '04', 'RUDI DWI SUSANTO, S.Pd.', 'Kediri, 02 Oktober 1998', '1998', '3506070210980001', '085608703889', 'GTT', 'L', 'Guru', '2025', 'Universitas Nusantara PGRI KEDIRI', 'Bimbingan dan Konseling', 'Sarjana', 'Bimbingan Dan Konseling', '2024', 'Jl. Gotong Royong Rt. 002 Rw. 006 Ds. Talun Kec. Talun Kab. Blitar Prov. Jawa Timur 66183', 'Kab. Blitar', '66183'],
        ];

        foreach ($rawGtk as $row) {
            $no = $row[0];
            $nigs = $row[1];
            $nigsFix = $row[2];
            $numgs = $row[3];
            $name = $row[4];
            $ttl = $row[5];
            $thLahir = $row[6];
            $nik = $row[7];
            $hp = $row[8];
            $statusGtk = $row[9];
            $gender = $row[10];
            $jabatan = $row[11];
            $tmt = $row[12];
            $fakultas = $row[13];
            $pendidikan = $row[15];
            $jurusan = $row[16];
            $thIjazah = $row[17];
            $alamatLengkap = $row[18];
            $kab = $row[19];
            $kodepos = $row[20];

            // Tentukan peran
            $roleId = $guruRole->id;
            if (str_contains(strtoupper($jabatan), 'KEPALA SEKOLAH')) {
                $roleId = $kepsekRole->id;
            } elseif ($nigsFix === '40601') { // SUPRIYANTO, ST (Senior & Supervisor)
                $roleId = $supervisorRole->id;
            }

            // Generate username unik ramah: e.g. guru_48902 atau kepsek untuk Endang Patmowati
            $username = 'guru_'.$nigsFix;
            if (str_contains(strtoupper($jabatan), 'KEPALA SEKOLAH')) {
                $username = 'kepsek';
            } elseif ($nigsFix === '41803') { // ROKHIM, S.Pd. (Waka Kurikulum)
                User::where('username', 'waka')->update([
                    'nigs_fix' => $nigsFix,
                    'nip' => null,
                    'nuptk' => null,
                    'nigs' => $nigs,
                    'numgs' => $numgs,
                    'nik' => $nik,
                    'phone' => $hp,
                    'birth_info' => $ttl,
                    'birth_year' => $thLahir,
                    'gtk_status' => $statusGtk,
                    'gender' => $gender,
                    'position' => 'Waka Kurikulum',
                    'tmt_year' => $tmt,
                    'faculty' => $fakultas,
                    'education_level' => $pendidikan,
                    'major' => $jurusan,
                    'graduation_year' => $thIjazah,
                    'address' => $alamatLengkap,
                    'city' => $kab,
                    'postal_code' => $kodepos,
                ]);

                continue;
            }

            User::updateOrCreate(
                ['nigs_fix' => $nigsFix],
                [
                    'name' => $name,
                    'username' => $username,
                    'email' => $nigsFix.'@smkpgrikras.sch.id',
                    'password' => Hash::make('password'),
                    'role_id' => $roleId,
                    'nip' => null,
                    'nuptk' => null,
                    'nigs' => $nigs,
                    'numgs' => $numgs,
                    'nigs_fix' => $nigsFix,
                    'nik' => $nik,
                    'birth_info' => $ttl,
                    'birth_year' => $thLahir,
                    'gtk_status' => $statusGtk,
                    'gender' => $gender,
                    'position' => $jabatan,
                    'tmt_year' => $tmt,
                    'faculty' => $fakultas,
                    'education_level' => $pendidikan,
                    'major' => $jurusan,
                    'graduation_year' => $thIjazah,
                    'address' => $alamatLengkap,
                    'city' => $kab,
                    'postal_code' => $kodepos,
                    'phone' => $hp,
                    'is_active' => true,
                ]
            );
        }
    }
}
