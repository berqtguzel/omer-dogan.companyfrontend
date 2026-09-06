<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use Tests\TestCase;

class ContactFormMailTest extends TestCase
{
    public function test_contact_mail_preview_is_available_on_localhost(): void
    {
        $this->get('http://localhost/_mail-preview/contact')
            ->assertOk();
    }

    public function test_contact_mail_renders_the_branded_html_template(): void
    {
        $mail = new ContactFormMail([
            'name' => 'Max Mustermann',
            'company' => 'Mustermann GmbH',
            'email' => 'max@example.com',
            'phone' => '+49 30 123456',
            'serviceType' => 'Unterhaltsreinigung',
            'message' => 'Bitte senden Sie uns ein Angebot.',
        ], [
            'general' => ['site_name' => 'Test Reinigung'],
            'colors' => ['site_primary_color' => '#14532d'],
        ]);

        $html = $mail->render();

        $this->assertStringContainsString('Neue Kontaktanfrage', $html);
        $this->assertStringContainsString('Test Reinigung', $html);
        $this->assertStringContainsString('#14532d', $html);
        $this->assertStringContainsString('mailto:max@example.com', $html);
        $this->assertStringContainsString('Bitte senden Sie uns ein Angebot.', $html);
    }

    public function test_contact_mail_uses_panel_branding_color_variants(): void
    {
        $mail = new ContactFormMail([
            'name' => 'Max Mustermann',
            'company' => null,
            'email' => 'max@example.com',
            'phone' => null,
            'serviceType' => null,
            'message' => 'Test',
        ], [
            'general' => ['site_name' => 'Panel Site'],
            'branding' => [
                'colors' => [
                    'primary_color' => '#0f172a',
                    'accent_color' => '#fbbf24',
                ],
            ],
        ]);

        $html = $mail->render();

        $this->assertStringContainsString('#0f172a', $html);
        $this->assertStringContainsString('#fbbf24', $html);
        $this->assertStringContainsString('color:#172033', $html);
    }

    public function test_contact_mail_removes_line_breaks_from_the_subject_name(): void
    {
        $mail = new ContactFormMail([
            'name' => "Example\r\nBcc: injected@example.test",
            'email' => 'sender@example.test',
            'message' => 'Test',
        ]);

        $subject = $mail->build()->subject;
        $this->assertStringNotContainsString("\r", $subject);
        $this->assertStringNotContainsString("\n", $subject);
    }
}
