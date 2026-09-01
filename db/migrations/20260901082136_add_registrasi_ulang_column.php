<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddRegistrasiUlangColumn extends AbstractMigration
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
        $table = $this->table('peserta');
        
        if (!$table->hasColumn('registrasi_ulang')) {
            $table->addColumn('registrasi_ulang', 'enum', [
                'values' => ['ya', 'tidak'],
                'default' => 'tidak',
                'null' => false,
                'after' => 'status_pembayaran',
            ])->update();
        }
    }
}
