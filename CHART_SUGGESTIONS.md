# Saran Chart & Grafik untuk Dashboard Siswa

Berdasarkan data yang tersedia di dashboard siswa, berikut adalah saran chart/grafik yang bisa ditampilkan:

## 1. **Line Chart - Trend Nilai (7 Hari Terakhir)**
   - **Data**: `trend_data['rata_nilai']` (sudah ada)
   - **Tujuan**: Menampilkan perkembangan nilai rata-rata siswa dalam 7 hari terakhir
   - **Manfaat**: Siswa bisa melihat apakah nilainya meningkat atau menurun

## 2. **Bar Chart - Aktivitas Belajar (Soal Selesai per Hari)**
   - **Data**: `trend_data['soal_selesai']` (sudah ada)
   - **Tujuan**: Menampilkan jumlah soal yang diselesaikan setiap hari
   - **Manfaat**: Memotivasi siswa untuk konsisten belajar setiap hari

## 3. **Stacked Bar Chart - Perbandingan Soal Aktif vs Selesai**
   - **Data**: `trend_data['soal_aktif']` dan `trend_data['soal_selesai']` (sudah ada)
   - **Tujuan**: Membandingkan soal aktif dengan soal yang sudah diselesaikan
   - **Manfaat**: Siswa bisa melihat progress dan backlog soal yang harus dikerjakan

## 4. **Donut Chart - Progress Belajar**
   - **Data**: `stats['total_soal_selesai']` vs `stats['total_soal_aktif']`
   - **Tujuan**: Menampilkan persentase soal yang sudah dikerjakan
   - **Manfaat**: Visualisasi progress belajar yang mudah dipahami

## 5. **Bar Chart - Nilai per Mata Pelajaran**
   - **Data**: Perlu query baru - rata-rata nilai per mata pelajaran
   - **Tujuan**: Menampilkan performa siswa di setiap mata pelajaran
   - **Manfaat**: Siswa bisa melihat mata pelajaran mana yang perlu lebih banyak latihan

## 6. **Line Chart - Trend Aktivitas Belajar (Bulanan)**
   - **Data**: Perlu query baru - jumlah soal selesai per bulan
   - **Tujuan**: Menampilkan trend aktivitas belajar dalam beberapa bulan terakhir
   - **Manfaat**: Melihat pola belajar jangka panjang

## 7. **Pie Chart - Distribusi Status Presensi**
   - **Data**: `presensi_summary` (sudah ada)
   - **Tujuan**: Menampilkan persentase hadir, terlambat, tidak hadir, izin
   - **Manfaat**: Siswa bisa melihat rekam jejak kehadiran

## 8. **Area Chart - Trend Nilai & Aktivitas (Kombinasi)**
   - **Data**: Kombinasi `trend_data['rata_nilai']` dan `trend_data['soal_selesai']`
   - **Tujuan**: Menampilkan korelasi antara aktivitas belajar dengan nilai
   - **Manfaat**: Memahami hubungan antara konsistensi belajar dengan hasil

## Rekomendasi Prioritas:
1. **Line Chart - Trend Nilai** (mudah, data sudah ada)
2. **Donut Chart - Progress Belajar** (mudah, data sudah ada)
3. **Bar Chart - Aktivitas Belajar** (mudah, data sudah ada)
4. **Bar Chart - Nilai per Mata Pelajaran** (perlu query baru, tapi sangat informatif)

