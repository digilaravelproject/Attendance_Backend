<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'page_name' => 'Terms & Conditions',
                'page_type' => 'terms_condition',
                'description' => <<<'HTML'
<h2>Terms and Conditions</h2>
<p>By accessing or using this attendance management service, you agree to these terms and conditions.</p>
<h3>Use of the Service</h3>
<p>You must provide accurate information, keep your account credentials secure, and use the service only for lawful attendance and workforce-management purposes.</p>
<h3>Availability and Changes</h3>
<p>We may improve, update, or temporarily suspend parts of the service when maintenance, security, or operational needs require it.</p>
<h3>Limitation of Liability</h3>
<p>To the extent permitted by law, we are not responsible for indirect or consequential loss arising from misuse of the service or circumstances outside our reasonable control.</p>
<h3>Contact</h3>
<p>If you have questions about these terms, please contact the administrator of this service.</p>
HTML,
                'status' => true,
            ],
            [
                'page_name' => 'Privacy Policy',
                'page_type' => 'privacy_policy',
                'description' => <<<'HTML'
<h2>Privacy Policy</h2>
<p>This policy explains how information is collected, used, and protected when you use this attendance management service.</p>
<h3>Information We Collect</h3>
<p>We may process account details, employee information, attendance records, work schedules, leave requests, and technical information needed to operate and secure the service.</p>
<h3>How We Use Information</h3>
<p>Information is used to provide attendance and workforce features, manage access, produce authorized reports, maintain security, and comply with applicable legal obligations.</p>
<h3>Data Protection</h3>
<p>Reasonable administrative and technical safeguards are used to protect information from unauthorized access, alteration, disclosure, or loss.</p>
<h3>Your Choices</h3>
<p>For questions, corrections, or requests concerning your information, please contact the administrator of this service.</p>
HTML,
                'status' => true,
            ],
            [
                'page_name' => 'Contact Us',
                'page_type' => 'contact_us',
                'description' => <<<'HTML'
<h2>Contact Us</h2>
<p>If you have any questions, need technical assistance, or want to share feedback, please contact the administrator of this attendance management service.</p>
<h3>Support</h3>
<p>When requesting help, include your name, organization, and a clear description of the issue so the support team can assist you efficiently.</p>
<p>We will respond as soon as reasonably possible during normal business hours.</p>
HTML,
                'status' => true,
            ],
        ];

        foreach ($pages as $page) {
            Page::query()->updateOrCreate(
                ['page_type' => $page['page_type']],
                $page,
            );
        }
    }
}
