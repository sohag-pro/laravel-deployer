<?php

namespace Tests\Unit;

use App\Support\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseTest extends TestCase
{
    public function test_dump_path_appends_gz_when_gzip_enabled(): void
    {
        $db = new Database('app', 'root', 'secret', '127.0.0.1', '3306', gzip: true);

        $this->assertSame('/db/app-2024.sql.gz', $db->dumpPath('/db/app-2024.sql'));
        // Already compressed paths are left alone.
        $this->assertSame('/db/app.sql.gz', $db->dumpPath('/db/app.sql.gz'));
    }

    public function test_dump_path_is_plain_when_gzip_disabled(): void
    {
        $db = new Database('app', 'root', 'secret', '127.0.0.1', '3306', gzip: false);

        $this->assertSame('/db/app-2024.sql', $db->dumpPath('/db/app-2024.sql'));
    }

    public function test_is_configured_requires_name_and_user(): void
    {
        $this->assertTrue((new Database('app', 'root', 'secret'))->isConfigured());
        $this->assertFalse((new Database(null, 'root', 'secret'))->isConfigured());
        $this->assertFalse((new Database('app', null, 'secret'))->isConfigured());
    }

    public function test_restore_throws_for_missing_file(): void
    {
        $this->expectException(RuntimeException::class);

        (new Database('app', 'root', 'secret'))->restore('/no/such/dump.sql');
    }
}
