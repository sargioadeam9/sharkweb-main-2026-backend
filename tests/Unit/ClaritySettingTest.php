<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Settings\GeneralSettings;

class ClaritySettingTest extends TestCase
{
    public function test_extracts_clarity_project_id_from_script_snippet(): void
    {
        $snippet = <<<HTML
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "pvj7w1e49b");
</script>
HTML;

        $trimmed = trim($snippet);
        $extracted = null;

        if (preg_match('/clarity[",\s\']+script[",\s\']+["\']([a-zA-Z0-9]+)["\']/i', $trimmed, $m)) {
            $extracted = $m[1];
        }

        $this->assertSame('pvj7w1e49b', $extracted);
    }

    public function test_extracts_clarity_project_id_from_plain_id(): void
    {
        $id = '  pvj7w1e49b  ';
        $trimmed = trim($id);
        $extracted = trim(strip_tags($trimmed));

        $this->assertSame('pvj7w1e49b', $extracted);
    }

    public function test_frontend_array_includes_clarity_project_id_when_set(): void
    {
        $settings = (new \ReflectionClass(GeneralSettings::class))->newInstanceWithoutConstructor();
        $settings->site_name = 'Test Site';
        $settings->contact_email = 'test@example.com';
        $settings->contact_phone = '123456';
        $settings->whatsapp_number = '123456';
        $settings->contact_location = 'Loc';
        $settings->twitter_url = null;
        $settings->founder_email = 'founder@example.com';
        $settings->founder_phone = '123';
        $settings->founder_avatar_path = '';
        $settings->default_meta_title = 'Title';
        $settings->default_meta_description = 'Desc';
        $settings->ga4_measurement_id = null;
        $settings->gtm_container_id = null;
        $settings->meta_pixel_id = null;
        $settings->tiktok_pixel_id = null;
        $settings->clarity_project_id = 'pvj7w1e49b';
        $settings->footer_blurb = 'Blurb';
        $settings->copyright_text = 'Copyright';
        $settings->support_widget_enabled = false;
        $settings->support_status_label = 'Online';
        $settings->support_offer = 'Offer';
        $settings->support_intro = 'Intro';
        $settings->support_name = 'Name';
        $settings->support_role = 'Role';
        $settings->support_verified = true;

        $frontend = $settings->toFrontendArray();
        $this->assertArrayHasKey('analytics', $frontend);
        $this->assertSame('pvj7w1e49b', $frontend['analytics']['clarity_project_id'] ?? null);
    }
}
