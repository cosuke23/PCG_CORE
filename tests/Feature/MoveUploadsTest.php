<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** pcg:move-uploads copies only: originals stay, conflicts are never overwritten. */
class MoveUploadsTest extends TestCase
{
    private string $src;
    private string $dst;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/pcg-move-' . uniqid();
        $this->src = "{$base}/src";
        $this->dst = "{$base}/private";
        File::ensureDirectoryExists("{$this->src}/documents_toupload/ABC-1");
        File::ensureDirectoryExists("{$this->src}/selfies");
        file_put_contents("{$this->src}/documents_toupload/ABC-1/nbi.pdf", '%PDF-1 sample');
        file_put_contents("{$this->src}/selfies/a.png", 'png');
        config(['pcg.upload_root' => $this->src, 'pcg.selfie_dir' => "{$this->src}/selfies"]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->src));
        parent::tearDown();
    }

    public function test_dry_run_copies_nothing()
    {
        $this->artisan('pcg:move-uploads', ['--target' => $this->dst])->expectsOutputToContain('DRY RUN')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($this->dst);
    }

    public function test_apply_copies_and_keeps_originals_and_never_overwrites()
    {
        // a different file already at the target must survive
        File::ensureDirectoryExists("{$this->dst}/selfies");
        file_put_contents("{$this->dst}/selfies/a.png", 'other');

        $this->artisan('pcg:move-uploads', ['--target' => $this->dst, '--apply' => true])->assertFailed();

        $this->assertSame('%PDF-1 sample', file_get_contents("{$this->dst}/documents_toupload/ABC-1/nbi.pdf"));
        $this->assertSame('other', file_get_contents("{$this->dst}/selfies/a.png"));
        $this->assertFileExists("{$this->src}/documents_toupload/ABC-1/nbi.pdf");
        $this->assertFileExists("{$this->src}/selfies/a.png");

        // after resolving the conflict, a re-run succeeds and copies nothing twice
        unlink("{$this->dst}/selfies/a.png");
        $this->artisan('pcg:move-uploads', ['--target' => $this->dst, '--apply' => true])
            ->expectsOutputToContain('Originals were NOT removed')->assertSuccessful();
        $this->assertSame('png', file_get_contents("{$this->dst}/selfies/a.png"));
    }

    public function test_target_inside_public_is_refused()
    {
        $this->artisan('pcg:move-uploads', ['--target' => public_path('private-uploads'), '--apply' => true])->assertFailed();
        $this->assertDirectoryDoesNotExist(public_path('private-uploads'));
    }
}
