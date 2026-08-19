<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitDatabaseTables extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        // 1. Tabel izin
        $this->table('izin')
            ->addColumn('kelompok', 'string', ['limit' => 100])
            ->addColumn('nama', 'string', ['limit' => 255])
            ->addColumn('jenis_kelamin', 'enum', ['values' => ['Laki-laki', 'Perempuan']])
            ->addColumn('pakai_tabungan', 'enum', ['values' => ['yes', 'no'], 'default' => 'no'])
            ->addColumn('file_izin', 'string', ['limit' => 255])
            ->addColumn('status', 'enum', ['values' => ['perlu_verifikasi', 'diterima', 'ditolak'], 'default' => 'perlu_verifikasi'])
            ->addColumn('diproses_oleh', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->create();

        // 2. Tabel log_keuangan
        $this->table('log_keuangan')
            ->addColumn('tanggal', 'date')
            ->addColumn('keterangan', 'string', ['limit' => 255])
            ->addColumn('jenis', 'enum', ['values' => ['masuk', 'keluar']])
            ->addColumn('sumber_pemasukan', 'enum', ['values' => ['peserta', 'kas', 'anggaran desa'], 'null' => true])
            ->addColumn('divisi_pengeluaran', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('nota', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('jumlah', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->create();

        // 3. Tabel sesi_presensi
        $this->table('sesi_presensi')
            ->addColumn('nama_sesi', 'string', ['limit' => 255])
            ->addColumn('tanggal_sesi', 'date')
            ->addColumn('waktu_sesi', 'string', ['limit' => 100])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['nama_sesi'], ['unique' => true])
            ->create();

        // 4. Tabel log_presensi
        $this->table('log_presensi')
            ->addColumn('id_peserta', 'integer')
            ->addColumn('id_sesi', 'integer')
            ->addColumn('status', 'enum', ['values' => ['Belum Presensi', 'Hadir', 'Terlambat', 'Izin'], 'default' => 'Belum Presensi'])
            ->addColumn('waktu_presensi', 'timestamp', ['null' => true])
            ->addColumn('keterangan', 'text', ['null' => true])
            ->addIndex(['id_peserta', 'id_sesi'], ['unique' => true, 'name' => 'unik_peserta_sesi'])
            ->addIndex(['id_sesi'], ['name' => 'id_sesi'])
            ->create();

        // 5. Tabel peserta
        $this->table('peserta')
            ->addColumn('kelompok', 'string', ['limit' => 100])
            ->addColumn('nama', 'string', ['limit' => 255])
            ->addColumn('jenis_kelamin', 'enum', ['values' => ['Laki-laki', 'Perempuan']])
            ->addColumn('barcode', 'string', ['limit' => 255])
            ->addColumn('pakai_tabungan', 'enum', ['values' => ['yes', 'no'], 'default' => 'no'])
            ->addColumn('metode_pembayaran', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('bukti_pembayaran', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('status_pembayaran', 'enum', ['values' => ['belum_diverifikasi', 'lunas', 'ditolak'], 'default' => 'belum_diverifikasi'])
            ->addColumn('terima_totebag', 'enum', ['values' => ['ya', 'tidak'], 'default' => 'tidak'])
            ->addColumn('terima_idcard', 'enum', ['values' => ['ya', 'tidak'], 'default' => 'tidak'])
            ->addColumn('dibayar_pada', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['barcode'], ['unique' => true])
            ->create();

        // 6. Tabel sesi_penunggu
        $this->table('sesi_penunggu')
            ->addColumn('nama_sesi', 'string', ['limit' => 255])
            ->addColumn('waktu_sesi', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('tanggal_sesi', 'date', ['null' => true])
            ->addColumn('jumlah_penunggu', 'integer', ['default' => 2])
            ->addColumn('nama_penunggu', 'text', ['null' => true])
            ->create();

        // 7. Tabel surat_izin_terbuat
        $this->table('surat_izin_terbuat')
            ->addColumn('jenis_surat', 'string', ['limit' => 100])
            ->addColumn('nama_peserta', 'string', ['limit' => 255])
            ->addColumn('kelompok', 'string', ['limit' => 100])
            ->addColumn('detail_surat', 'text', ['null' => true])
            ->addColumn('nama_file_pdf', 'string', ['limit' => 255])
            ->addColumn('dibuat_pada', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->create();

        // 8. Tabel surat_undangan
        $this->table('surat_undangan')
            ->addColumn('jenis_undangan', 'enum', ['values' => ['Nasehat Pembukaan', 'Nasehat Penutupan', 'Nasehat Shubuh', 'Makalah CAI']])
            ->addColumn('nama_pemateri', 'string', ['limit' => 255])
            ->addColumn('topik_materi', 'string', ['limit' => 255])
            ->addColumn('tanggal_acara', 'date')
            ->addColumn('waktu_acara', 'string', ['limit' => 50])
            ->addColumn('nama_file_pdf', 'string', ['limit' => 255])
            ->addColumn('dibuat_pada', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true])
            ->create();

        // 9. Tabel users
        $tableUsers = $this->table('users');
        $tableUsers->addColumn('nama', 'string', ['limit' => 255])
            ->addColumn('username', 'string', ['limit' => 100])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('role', 'string', ['limit' => 50])
            ->addColumn('kode_barcode', 'string', ['limit' => 255])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['username'], ['unique' => true])
            ->addIndex(['kode_barcode'], ['unique' => true])
            ->create();

        // 10. Insert Akun Super Admin langsung di migrasi
        $plainPassword = 'adminutamacai2025';
        $dataAdmin = [
            [
                'nama'         => 'Panca Aulia',
                'username'     => 'superadmincai2025',
                'password'     => password_hash($plainPassword, PASSWORD_DEFAULT),
                'role'         => 'superadmin',
                'kode_barcode' => 'CAI-2c644d8a960e2c5086eb87f98b87407b',
                'created_at'   => date('Y-m-d H:i:s'),
            ]
        ];

        $tableUsers->insert($dataAdmin)->saveData();
    }
}
