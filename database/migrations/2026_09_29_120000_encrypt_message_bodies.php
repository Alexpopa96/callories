<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Encrypts the chat text written before the models started doing it; already encrypted rows are left alone. */
    public function up(): void
    {
        $this->each('messages', ['body'], fn (?string $value) => $this->isEncrypted($value) ? $value : Crypt::encryptString($value));
        $this->each('message_reports', ['body', 'reason'], fn (?string $value) => $this->isEncrypted($value) ? $value : Crypt::encryptString($value));
    }

    public function down(): void
    {
        $this->each('messages', ['body'], fn (?string $value) => $this->isEncrypted($value) ? Crypt::decryptString($value) : $value);
        $this->each('message_reports', ['body', 'reason'], fn (?string $value) => $this->isEncrypted($value) ? Crypt::decryptString($value) : $value);
    }

    private function each(string $table, array $columns, callable $transform): void
    {
        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $transform) {
            foreach ($rows as $row) {
                $changes = [];

                foreach ($columns as $column) {
                    if ($row->{$column} !== null) {
                        $changes[$column] = $transform($row->{$column});
                    }
                }

                if ($changes) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            }
        });
    }

    private function isEncrypted(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
