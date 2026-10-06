<?php

// Marketing site — Terms of Use. Spanish in lang/es/site/terms.php.
// Same structure as lang/en/site/privacy.php (rendered by site/pages/legal.blade.php);
// :contact_url and :privacy_url are filled in by the view.
return [
    'meta' => [
        'title' => 'Terms of Use',
        'description' => 'The terms that apply when you use the Quality Cleaning Plus website, including our job openings and application.',
    ],

    'title' => 'Terms of Use',
    'updated' => 'Last updated: October 5, 2026',

    'intro' => [
        'These Terms of Use govern your use of qualitycleanplus.com (the “Site”), operated by Quality Cleaning Plus, Inc. (“Quality Cleaning Plus,” “we,” “us”). By using the Site, you agree to these terms. If you do not agree, please do not use the Site.',
    ],

    'sections' => [
        [
            'heading' => 'Using the Site',
            'blocks' => [
                'The Site gives information about our staffing and cleaning services, lists job openings, and lets you contact us and apply for work. You may use it only for those purposes and only in line with these terms and the law.',
            ],
        ],
        [
            'heading' => 'Job openings and applications',
            'blocks' => [
                [
                    'Job openings describe positions at the time they are posted. They may change, be filled or be withdrawn at any time without notice.',
                    'Sending an application does not guarantee an interview or a job. Nothing on the Site is an offer of employment or creates an employment contract.',
                    'The information in your application must be true and complete. False or misleading information may disqualify you from consideration or, if you are hired, lead to the end of your employment.',
                    'Unless a written agreement signed by Quality Cleaning Plus says otherwise, employment with us is at will: either you or we may end it at any time.',
                    'Quality Cleaning Plus is an equal opportunity employer.',
                ],
            ],
        ],
        [
            'heading' => 'Information you send us',
            'blocks' => [
                'You are responsible for the information you submit through the Site and must send only your own information. How we handle it is explained in our <a href=":privacy_url">Privacy Policy</a>.',
            ],
        ],
        [
            'heading' => 'What you may not do',
            'blocks' => [
                [
                    'Submit false information, or information about another person without their permission.',
                    'Send spam, or use automated means to submit forms or collect content from the Site.',
                    'Try to gain unauthorized access to the Site or our systems, or interfere with how they work.',
                    'Upload or transmit viruses or other harmful code.',
                    'Use the Site for any unlawful purpose.',
                ],
            ],
        ],
        [
            'heading' => 'Our content',
            'blocks' => [
                'The Site and its content, including text, photos, graphics and logos, belong to Quality Cleaning Plus or its licensors and are protected by copyright and trademark law. The Quality Cleaning Plus name and logo are our trademarks. You may view and print pages for your own personal, non-commercial use; any other use requires our written permission.',
            ],
        ],
        [
            'heading' => 'Other websites and services',
            'blocks' => [
                'The Site links to websites and uses services run by others, such as Google reCAPTCHA and our social media pages. We are not responsible for their content or practices, and their own terms apply.',
            ],
        ],
        [
            'heading' => 'Disclaimer',
            'blocks' => [
                'The Site is provided “as is” and “as available.” We work to keep it accurate and available, but we do not promise that it will be error-free, uninterrupted or always up to date. To the fullest extent the law allows, we disclaim all warranties, express or implied, including warranties of merchantability, fitness for a particular purpose and non-infringement.',
            ],
        ],
        [
            'heading' => 'Limitation of liability',
            'blocks' => [
                'To the fullest extent the law allows, Quality Cleaning Plus will not be liable for any indirect, incidental, special or consequential damages arising from your use of, or inability to use, the Site. This does not limit any liability that cannot be limited under applicable law.',
            ],
        ],
        [
            'heading' => 'Governing law',
            'blocks' => [
                'These terms are governed by the laws of the State of Texas, without regard to its conflict-of-law rules. Any dispute about the Site or these terms will be handled in the state or federal courts located in Dallas County, Texas.',
            ],
        ],
        [
            'heading' => 'Changes to these terms',
            'blocks' => [
                'We may update these terms from time to time. We will post the new version on this page and change the date at the top. Using the Site after a change means you accept the updated terms.',
            ],
        ],
        [
            'heading' => 'Contact us',
            'blocks' => [
                'Questions about these terms? <a href=":contact_url">Contact us</a>, call <a href="tel:214-271-5595">214-271-5595</a>, or write to us at Quality Cleaning Plus, Inc., 1720 Regal Row, Suite 126, Dallas, Texas 75235.',
            ],
        ],
    ],
];
