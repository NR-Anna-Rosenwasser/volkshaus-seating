<?php

namespace App\Console\Commands;

use App\Services\AdminAlert;
use App\Support\StatusLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('seating:backup')]
#[Description('Dump the database to the backup folder and remove old dumps')]
class BackupDatabase extends Command
{
    public const LAST_BACKUP_KEY = 'seating.last-backup';

    private const TIMEOUT_SECONDS = 300;

    public function handle(AdminAlert $alert): int
    {
        $folder = config('seating.backup_path');
        $target = "{$folder}/seating-".now()->format('Y-m-d-His').'.dump';
        $partial = "{$target}.partial";
        $database = config('database.connections.'.config('database.default'));

        File::ensureDirectoryExists($folder);

        // The password goes through the environment, so it never shows up in a process list.
        $result = Process::timeout(self::TIMEOUT_SECONDS)
            ->env(['PGPASSWORD' => (string) $database['password']])
            ->run([
                'pg_dump', '--format=custom',
                '--host', (string) $database['host'],
                '--port', (string) $database['port'],
                '--username', (string) $database['username'],
                '--file', $partial,
                (string) $database['database'],
            ]);

        if ($result->failed()) {
            File::delete($partial);
            $alert->send('Backup fehlgeschlagen', 'seating:backup konnte keinen Dump erstellen. Details stehen im Log.');
            $this->error($result->errorOutput());

            return self::FAILURE;
        }

        // Renamed only when complete: a half-written file must never look like a backup.
        File::move($partial, $target);
        StatusLog::put(self::LAST_BACKUP_KEY, now()->toIso8601String());

        $removed = $this->removeOldDumps($folder);
        $this->info("Backup written. {$removed} old dumps removed.");

        return self::SUCCESS;
    }

    private function removeOldDumps(string $folder): int
    {
        $cutoff = now()->subDays((int) config('seating.backup_keep_days'))->getTimestamp();
        $old = array_filter(File::glob("{$folder}/seating-*.dump"), fn (string $file): bool => File::lastModified($file) < $cutoff);

        File::delete($old);

        return count($old);
    }
}
