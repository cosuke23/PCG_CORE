<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    public function test_csp_header_uses_a_fresh_nonce_that_matches_inline_scripts()
    {
        $first = $this->get('/unit/login');
        $csp = $first->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);

        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $this->assertNotEmpty($m[1] ?? null);

        $second = $this->get('/unit/login')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString($m[1], $second, 'nonce must change per request');
    }

    public function test_inline_scripts_in_pages_carry_the_nonce()
    {
        $response = $this->get('/contact'); // layouts/app has inline scripts
        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);

        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $response->getContent(), $scripts);
        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[1] as $attrs) {
            $this->assertStringContainsString('nonce="' . $m[1] . '"', $attrs);
        }
    }

    public function test_views_have_no_inline_event_handlers()
    {
        $offenders = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php') || str_contains($file->getFilename(), 'backup')) {
                continue;
            }
            if (preg_match('/\son(click|dblclick|change|submit|input|load|error|keyup|keydown|mouseover)\s*=/i', $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Use data-on-click (public/js/pcg-ui.js) instead: CSP blocks inline handlers.');
    }

    public function test_report_only_and_off_modes()
    {
        config(['pcg.csp' => 'report']);
        $r = $this->get('/unit/login');
        $this->assertNull($r->headers->get('Content-Security-Policy'));
        $this->assertNotNull($r->headers->get('Content-Security-Policy-Report-Only'));

        config(['pcg.csp' => 'off']);
        $this->assertNull($this->get('/unit/login')->headers->get('Content-Security-Policy'));
    }

    public function test_script_src_uses_strict_dynamic_and_eval_only_where_flagged()
    {
        $login = $this->get('/unit/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'strict-dynamic'", $login);
        $this->assertStringNotContainsString("'unsafe-eval'", $login);
        $this->assertStringNotContainsString('tile.openstreetmap.org', $login);

        // layouts/app loads Alpine (needs eval); the flag must not leak to the next request
        $this->assertStringContainsString("'unsafe-eval'", $this->get('/contact')->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("'unsafe-eval'", $this->get('/unit/login')->headers->get('Content-Security-Policy'));
    }

    public function test_every_external_script_tag_carries_the_nonce()
    {
        // with 'strict-dynamic', 'self' is ignored: a <script src> without the nonce does not run
        $offenders = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php') || str_contains($file->getFilename(), 'backup')) {
                continue;
            }
            preg_match_all('/<script\b[^>]*\bsrc=[^>]*>/i', $file->getContents(), $m);
            foreach ($m[0] as $tag) {
                if (!str_contains($tag, 'nonce=')) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)));
    }

    public function test_map_hosts_are_allowed_only_by_the_upload_documents_page()
    {
        $flagged = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_contains($file->getContents(), "'csp.allow_osm'")) {
                $flagged[] = str_replace('\\', '/', $file->getRelativePathname());
            }
        }

        $this->assertSame(['applicant/upload-documents.blade.php'], $flagged);
    }
}
