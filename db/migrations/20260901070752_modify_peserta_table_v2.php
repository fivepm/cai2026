<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ModifyPesertaTableV2 extends AbstractMigration
{
    /**
     * Migrate Up:
     * - Hapus kolom: pakai_tabungan, bukti_pembayaran, terima_totebag (jika masih ada)
     * - Tambah kolom: terima_jersey (enum ya/tidak) (jika belum ada)
     * - Set default metode_pembayaran = Cash
     */
    public function up(): void
    {
        // Update data NULL/kosong pada metode_pembayaran terlebih dahulu
        $this->execute("UPDATE peserta SET metode_pembayaran = 'Cash' WHERE metode_pembayaran IS NULL OR metode_pembayaran = ''");

        // Set default dan NOT NULL pada metode_pembayaran
        $this->execute("ALTER TABLE peserta MODIFY COLUMN metode_pembayaran VARCHAR(50) NOT NULL DEFAULT 'Cash'");

        $table = $this->table('peserta');
        $columns = $this->getAdapter()->getColumns('peserta');
        $columnNames = array_map(fn($c) => $c->getName(), $columns);

        // Hapus kolom yang tidak diperlukan (jika masih ada)
        if (in_array('pakai_tabungan', $columnNames)) {
            $table->removeColumn('pakai_tabungan');
        }
        if (in_array('bukti_pembayaran', $columnNames)) {
            $table->removeColumn('bukti_pembayaran');
        }
        if (in_array('terima_totebag', $columnNames)) {
            $table->removeColumn('terima_totebag');
        }
        $table->update();

        // Tambah kolom terima_jersey jika belum ada
        $table = $this->table('peserta');
        $columns = $this->getAdapter()->getColumns('peserta');
        $columnNames = array_map(fn($c) => $c->getName(), $columns);
        if (!in_array('terima_jersey', $columnNames)) {
            $table->addColumn('terima_jersey', 'enum', [
                      'values'  => ['ya', 'tidak'],
                      'default' => 'tidak',
                      'after'   => 'terima_idcard',
                  ])
                  ->update();
        }
    }

    /**
     * Migrate Down (rollback):
     * - Kembalikan kolom: pakai_tabungan, bukti_pembayaran, terima_totebag
     * - Hapus kolom: terima_jersey
     * - Kembalikan metode_pembayaran ke nullable
     */
    public function down(): void
    {
        $table = $this->table('peserta');
        $columns = $this->getAdapter()->getColumns('peserta');
        $columnNames = array_map(fn($c) => $c->getName(), $columns);

        // Hapus kolom terima_jersey jika ada
        if (in_array('terima_jersey', $columnNames)) {
            $table->removeColumn('terima_jersey')->update();
        }

        // Kembalikan kolom-kolom yang dihapus
        $table = $this->table('peserta');
        $columns = $this->getAdapter()->getColumns('peserta');
        $columnNames = array_map(fn($c) => $c->getName(), $columns);

        if (!in_array('pakai_tabungan', $columnNames)) {
            $table->addColumn('pakai_tabungan', 'enum', [
                'values'  => ['yes', 'no'],
                'default' => 'no',
                'after'   => 'barcode',
            ]);
        }
        if (!in_array('bukti_pembayaran', $columnNames)) {
            $table->addColumn('bukti_pembayaran', 'string', [
                'limit' => 255,
                'null'  => true,
                'after' => 'metode_pembayaran',
            ]);
        }
        if (!in_array('terima_totebag', $columnNames)) {
            $table->addColumn('terima_totebag', 'enum', [
                'values'  => ['ya', 'tidak'],
                'default' => 'tidak',
                'after'   => 'status_pembayaran',
            ]);
        }
        $table->update();

        // Kembalikan metode_pembayaran ke nullable
        $this->execute("ALTER TABLE peserta MODIFY COLUMN metode_pembayaran VARCHAR(50) NULL DEFAULT NULL");
    }
}