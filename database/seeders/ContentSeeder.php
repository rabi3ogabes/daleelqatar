<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

/** Initial CMS pages and FAQs. Administrators edit these from the dashboard. */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'about' => [
                'دليل قطر', 'About Daleel Qatar',
                <<<'MD'
**دليل قطر** منصة رقمية قطرية تجمع الأعمال والخدمات في مكان واحد، لتساعدك على اكتشاف ما تحتاجه في الدوحة ولوسيل والريان وجميع بلديات قطر بسرعة وثقة.

## رسالتنا
أن نكون المرجع الأول والأكثر موثوقية للأعمال في قطر، وأن نمنح أصحاب الأعمال واجهة رقمية أنيقة تعرّف الناس بخدماتهم.

## لماذا دليل قطر؟
- **معلومات موثوقة:** تُراجع كل قائمة قبل نشرها، وتظهر شارة التوثيق فقط بعد التحقق من المستندات الرسمية.
- **تجربة ثنائية اللغة:** محتوى كامل باللغتين العربية والإنجليزية.
- **بحث ذكي:** ابحث بالاسم أو الخدمة أو الفئة أو المنطقة.
- **لأصحاب الأعمال:** أضف نشاطك التجاري مجاناً، وأدر بياناتك وعروضك وتقييمات عملائك من لوحة تحكم خاصة.

## تواصل معنا
يسعدنا تواصلك عبر صفحة **اتصل بنا**.
MD,
                <<<'MD'
**Daleel Qatar** is a Qatari digital platform that brings businesses and services together in one place, helping you discover what you need in Doha, Lusail, Al Rayyan and every municipality of Qatar — quickly and with confidence.

## Our mission
To be the most trusted reference for businesses in Qatar, and to give business owners an elegant digital presence that introduces their services to the people who need them.

## Why Daleel Qatar?
- **Trustworthy information:** every listing is reviewed before it is published, and the verification badge only appears after official documents are checked.
- **Truly bilingual:** complete content in Arabic and English.
- **Smart search:** search by name, service, category or area.
- **Built for business owners:** list your business for free and manage your details, offers and customer reviews from a dedicated dashboard.

## Get in touch
We would love to hear from you through our **Contact** page.
MD,
            ],
            'privacy' => [
                'سياسة الخصوصية', 'Privacy Policy',
                <<<'MD'
توضح هذه السياسة كيف يجمع **دليل قطر** المعلومات ويستخدمها ويحميها.

## المعلومات التي نجمعها
- **بيانات الحساب:** الاسم والبريد الإلكتروني ورقم الهاتف (اختياري) عند التسجيل.
- **بيانات الأعمال:** المعلومات التي يقدمها أصحاب الأعمال لنشرها في الدليل.
- **مستندات التحقق:** تُحفظ بشكل خاص ولا يطلع عليها إلا فريق المراجعة المخوّل.
- **بيانات الاستخدام:** إحصاءات مجمّعة مثل عدد مشاهدات الصفحات وعمليات البحث. لا نخزن عناوين IP بشكل مباشر، بل بصيغة مشفرة غير قابلة للاسترجاع.

## كيف نستخدم المعلومات
لتشغيل الدليل، ومراجعة القوائم، والتواصل معك بشأن حسابك، وتحسين الخدمة. لا نبيع بياناتك الشخصية.

## مصادر بيانات الأعمال
تُجمع قوائم الأعمال من أصحابها مباشرة ومن مصادر عامة متاحة مثل الأدلة التجارية المنشورة. تُعرض أرقام الهواتف وعناوين البريد الإلكتروني عند الطلب فقط حمايةً لها من الرسائل المزعجة. يمكن لصاحب أي نشاط طلب تصحيح بياناته أو حذفها في أي وقت عبر صفحة **اتصل بنا** أو بالمطالبة بملكية القائمة.

## ملفات تعريف الارتباط والتحليلات والإعلانات
- نستخدم ملفات تعريف ارتباط ضرورية لتسجيل الدخول وحماية النماذج.
- قد نستخدم **Google Analytics** لقياس الزيارات بشكل مجمّع.
- قد نعرض إعلانات عبر **Google AdSense**. يستخدم موردو الجهات الخارجية، ومنهم Google، ملفات تعريف الارتباط لعرض الإعلانات بناءً على زياراتك السابقة لهذا الموقع أو لمواقع أخرى. يتيح ملف تعريف الارتباط الإعلاني من Google له ولشركائه عرض إعلانات لك استناداً إلى زياراتك.
- يمكنك إيقاف الإعلانات المخصصة من [إعدادات الإعلانات في Google](https://adssettings.google.com)، أو من [www.aboutads.info](https://www.aboutads.info/choices). ويمكنك قبول ملفات تعريف الارتباط غير الضرورية أو رفضها من الشريط الذي يظهر عند زيارتك الأولى.

## حقوقك
يمكنك تحديث بياناتك أو طلب حذف حسابك في أي وقت من خلال إعدادات الحساب أو التواصل معنا.

## الأمان
نستخدم اتصالات مشفرة وصلاحيات وصول محددة وسجلات تدقيق لحماية البيانات.
MD,
                <<<'MD'
This policy explains how **Daleel Qatar** collects, uses and protects information.

## Information we collect
- **Account data:** your name, e-mail address and (optionally) phone number when you register.
- **Business data:** information business owners submit for publication in the directory.
- **Verification documents:** stored privately and only accessible to authorised reviewers.
- **Usage data:** aggregated statistics such as page views and searches. We do not store IP addresses directly — only a salted, non-reversible hash.

## How we use information
To operate the directory, review listings, contact you about your account and improve the service. We do not sell your personal data.

## Where business information comes from
Listings are provided by business owners and compiled from publicly available sources such as published business directories. Phone numbers and e-mail addresses are only shown on request, to protect them from spam. Any business can ask us to correct or remove its details at any time through our **Contact** page or by claiming the listing.

## Cookies, analytics and advertising
- We use essential cookies for signing in and protecting forms.
- We may use **Google Analytics** to measure traffic in aggregate.
- We may show ads through **Google AdSense**. Third-party vendors, including Google, use cookies to serve ads based on your prior visits to this website or other websites. Google's use of advertising cookies enables it and its partners to serve ads to you based on your visits to this and/or other sites on the Internet.
- You can opt out of personalised advertising in [Google Ads Settings](https://adssettings.google.com), or at [www.aboutads.info](https://www.aboutads.info/choices). You can accept or decline non-essential cookies in the banner shown on your first visit.

## Your rights
You can update your details or request deletion of your account at any time from your account settings or by contacting us.

## Security
We use encrypted connections, least-privilege access controls and audit logs to protect data.
MD,
            ],
            'terms' => [
                'الشروط والأحكام', 'Terms & Conditions',
                <<<'MD'
باستخدامك **دليل قطر** فإنك توافق على الشروط التالية.

## الحسابات
أنت مسؤول عن دقة معلومات حسابك والحفاظ على سرية كلمة المرور.

## قوائم الأعمال
- يجب أن تكون المعلومات المقدمة صحيحة وأن تكون مخولاً بتمثيل النشاط التجاري.
- تخضع جميع القوائم والعروض للمراجعة، ويحق لنا رفض أو تعليق أي محتوى مخالف.
- شارة التوثيق تُمنح فقط بعد التحقق من المستندات الرسمية.

## التقييمات
يجب أن تعكس التقييمات تجربة حقيقية. يُمنع نشر تقييمات مضللة أو مسيئة أو مدفوعة.

## المحتوى المحظور
يُمنع نشر أي محتوى يخالف القوانين المعمول بها في دولة قطر أو ينتهك حقوق الآخرين.

## حدود المسؤولية
يقدم الدليل المعلومات كما وردت من أصحاب الأعمال، ولا يتحمل مسؤولية التعاملات بين المستخدمين والأعمال.

MD,
                <<<'MD'
By using **Daleel Qatar** you agree to the following terms.

## Accounts
You are responsible for the accuracy of your account information and for keeping your password confidential.

## Business listings
- Information you submit must be accurate, and you must be authorised to represent the business.
- All listings and offers are subject to review, and we may reject or suspend content that breaches these terms.
- The verification badge is granted only after official documents have been checked.

## Reviews
Reviews must reflect a genuine experience. Misleading, abusive or paid reviews are not allowed.

## Prohibited content
Content that violates the laws of the State of Qatar or infringes the rights of others is prohibited.

## Limitation of liability
The directory presents information as provided by businesses and is not responsible for transactions between users and businesses.

MD,
            ],
        ];

        foreach ($pages as $slug => [$tAr, $tEn, $bAr, $bEn]) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title_ar' => $tAr, 'title_en' => $tEn, 'body_ar' => $bAr, 'body_en' => $bEn, 'is_published' => true,
            ]);
        }

        if (Faq::query()->exists()) {
            return;
        }

        $faqs = [
            ['general', 'ما هو دليل قطر؟', 'What is Daleel Qatar?', 'دليل قطر منصة ثنائية اللغة للبحث عن الأعمال والخدمات في جميع أنحاء قطر.', 'Daleel Qatar is a bilingual platform for finding businesses and services across Qatar.'],
            ['general', 'هل استخدام الدليل مجاني؟', 'Is the directory free to use?', 'نعم، البحث وتصفح الأعمال مجاني بالكامل.', 'Yes — searching and browsing businesses is completely free.'],
            ['general', 'ماذا تعني شارة التوثيق؟', 'What does the verification badge mean?', 'تعني أن فريقنا راجع المستندات الرسمية للنشاط التجاري مثل السجل التجاري.', 'It means our team has reviewed the business’s official documents, such as its commercial registration.'],
            ['users', 'كيف أكتب تقييماً؟', 'How do I write a review?', 'سجّل الدخول وأكّد بريدك الإلكتروني، ثم افتح صفحة النشاط التجاري واضغط على «اكتب تقييماً». تُراجع التقييمات قبل نشرها.', 'Sign in and verify your e-mail, then open the business page and choose “Write a review”. Reviews are moderated before publication.'],
            ['owners', 'كيف أضيف نشاطي التجاري؟', 'How do I add my business?', 'أنشئ حساباً مجانياً، ثم اختر «أضف نشاطك» واملأ البيانات وأرسلها للمراجعة.', 'Create a free account, choose “Add your business”, complete the details and submit it for review.'],
            ['owners', 'كم تستغرق مراجعة القائمة؟', 'How long does a listing review take?', 'نراجع القوائم عادة خلال يومي عمل، وستصلك رسالة عند اتخاذ القرار.', 'We usually review listings within two working days, and you will be notified of the decision.'],
            ['owners', 'نشاطي موجود في الدليل، كيف أطالب بملكيته؟', 'My business is already listed — how do I claim it?', 'افتح صفحة النشاط واضغط «طالب بملكية هذا النشاط»، ثم أرفق ما يثبت صلتك به.', 'Open the business page, choose “Claim this business” and attach proof of your connection to it.'],
            ['owners', 'هل يمكنني نشر العروض؟', 'Can I publish offers?', 'نعم، بعد اعتماد قائمتك يمكنك إضافة العروض والخصومات من لوحة التحكم، وتختفي تلقائياً بعد انتهاء مدتها.', 'Yes — once your listing is approved you can add offers from your dashboard; they disappear automatically when they expire.'],
        ];

        foreach ($faqs as $i => [$aud, $qAr, $qEn, $aAr, $aEn]) {
            Faq::query()->create([
                'audience' => $aud, 'question_ar' => $qAr, 'question_en' => $qEn,
                'answer_ar' => $aAr, 'answer_en' => $aEn, 'sort_order' => $i, 'is_active' => true,
            ]);
        }
    }
}
