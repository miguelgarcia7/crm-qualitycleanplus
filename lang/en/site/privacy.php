<?php

// Marketing site — Privacy Policy. Spanish in lang/es/site/privacy.php.
// Rendered by site/pages/legal.blade.php: each section is a heading plus blocks,
// where a string is a paragraph and a list is a bulleted list. Strings may hold
// simple HTML (links, <strong>); :contact_url, :terms_url are filled in by the view.
// Statements marked for confirmation in docs/80-plan/marketing-site-audit.md must
// be checked by the business (and counsel) before go-live.
return [
    'meta' => [
        'title' => 'Privacy Policy',
        'description' => 'How Quality Cleaning Plus collects, uses and protects the information you share through our website, including job applications.',
    ],

    'title' => 'Privacy Policy',
    'updated' => 'Last updated: October 8, 2026',

    'intro' => [
        'Quality Cleaning Plus, Inc. (“Quality Cleaning Plus,” “we,” “us”) provides staffing and cleaning services in the Dallas–Fort Worth area. This Privacy Policy explains what personal information we collect through qualitycleanplus.com (the “Site”), how we use and share it, and the choices you have.',
        'It covers information collected through the Site. Information you give us in person, or as an employee, is handled under our employment practices and applicable law. Use of the Site is also governed by our <a href=":terms_url">Terms of Use</a>.',
    ],

    'sections' => [
        [
            'heading' => 'Information we collect',
            'blocks' => [
                '<strong>Information you give us.</strong>',
                [
                    '<strong>Contact forms:</strong> your name, email address, phone number, the best time to call you and your message. Business inquiries also ask for your company name, address and the type of inquiry.',
                    '<strong>Employment applications:</strong> your name, email address, phone number and home address; date of birth; the position, pay and start date you want; whether you have reliable transportation; whether you are a U.S. citizen or otherwise eligible to work in the United States; whether you have worked for us or for another staffing agency before, and any non-compete agreement; your answer to our question about criminal convictions and any details you provide; and an emergency contact (name, phone, relationship and address).',
                ],
                '<strong>Information collected automatically.</strong> When you visit the Site, our servers and the services we use record technical information such as your IP address, browser and device type, the pages you view and the page that referred you.',
                [
                    '<strong>Cookies:</strong> the Site sets a session cookie and a security cookie that the forms need in order to work. Cloudflare, the network our hosting provider uses to deliver and protect the Site, may set a short-lived cookie to tell people apart from automated traffic. We also use Google Analytics, which sets its own cookies to measure how visitors use the Site.',
                    '<strong>Spam protection:</strong> our forms use Google reCAPTCHA, which collects information about your device and how you interact with the page and sends it to Google to tell people apart from automated spam.',
                ],
            ],
        ],
        [
            'heading' => 'How we use your information',
            'blocks' => [
                [
                    'To answer your questions and respond to requests for staffing or cleaning services.',
                    'To consider you for work: reviewing your application, contacting you about openings, matching you with positions and, if you are hired, setting up your employment.',
                    'To keep the Site and our forms secure and to prevent spam, fraud and abuse.',
                    'To understand how the Site is used so we can improve it.',
                    'To comply with the law and legal process, and to protect the rights and safety of our applicants, employees, clients and company.',
                ],
            ],
        ],
        [
            'heading' => 'How we share your information',
            'blocks' => [
                'We do not sell your personal information, and we do not share it for targeted advertising. We share it only as described here:',
                [
                    '<strong>Service providers</strong> that run the Site and our systems for us, such as website hosting (including Cloudflare, which delivers and protects the Site), data storage and email delivery. They may use your information only to provide those services to us.',
                    '<strong>Google</strong>, for Google Analytics and reCAPTCHA. Google’s use of that information is governed by the <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>.',
                    '<strong>Client businesses</strong> where we may place you, if you apply for work: we may share relevant details such as your name, the work you are looking for, your availability and your experience.',
                    '<strong>Legal and safety reasons:</strong> when the law requires it, to respond to legal process, or to protect the rights, property or safety of others.',
                    '<strong>Business transfers:</strong> if our company is merged, sold or reorganized, the information may be transferred as part of that transaction.',
                ],
            ],
        ],
        [
            'heading' => 'How long we keep it',
            'blocks' => [
                'We keep contact-form messages for as long as we need them to respond to you and for our business records. We keep employment applications so we can consider you for current and future openings and to meet our legal record-keeping obligations; applicant and employee records are generally kept for up to seven years after they are closed, unless the law requires us to keep them longer.',
            ],
        ],
        [
            'heading' => 'How we protect it',
            'blocks' => [
                'The Site uses encrypted (HTTPS) connections, and access to the information you send is limited to the staff who need it to do their jobs. No system is completely secure, but we work to protect your information and review our safeguards over time.',
            ],
        ],
        [
            'heading' => 'Your choices',
            'blocks' => [
                [
                    '<strong>See, correct or delete your information:</strong> <a href=":contact_url">contact us</a> and we will respond as applicable law requires. We may need to confirm your identity first, and we may have to keep some information, for example employment records the law requires us to retain.',
                    '<strong>Google Analytics:</strong> you can opt out with the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener">Google Analytics opt-out browser add-on</a>.',
                    '<strong>Cookies:</strong> you can block or delete cookies in your browser settings. If you block the Site’s own cookies, the forms will not work.',
                ],
            ],
        ],
        [
            'heading' => 'Children',
            'blocks' => [
                'The Site is not directed to children under 13, and we do not knowingly collect personal information from them. If you believe a child has sent us information, please contact us and we will delete it.',
            ],
        ],
        [
            'heading' => 'Other websites',
            'blocks' => [
                'The Site links to other websites, such as our social media pages. Their own privacy policies apply when you visit them.',
            ],
        ],
        [
            'heading' => 'Changes to this policy',
            'blocks' => [
                'We may update this policy from time to time. We will post the new version on this page and change the date at the top.',
            ],
        ],
        [
            'heading' => 'Contact us',
            'blocks' => [
                'If you have questions about this policy or your information, <a href=":contact_url">contact us</a>, call <a href="tel:214-271-5595">214-271-5595</a>, or write to us at Quality Cleaning Plus, Inc., 1720 Regal Row, Suite 126, Dallas, Texas 75235.',
            ],
        ],
    ],
];
