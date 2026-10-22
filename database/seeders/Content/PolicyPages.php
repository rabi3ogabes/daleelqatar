<?php

namespace Database\Seeders\Content;

/**
 * The site's policy pages (cookies, disclaimer, editorial policy, advertising)
 * and the privacy-policy section on visitor statistics. Used by the content
 * seeder for new installations and by a migration for the live site; once
 * created, administrators edit them like any other page.
 */
final class PolicyPages
{
    /** Marker heading of the visitor-statistics section added to the privacy policy. */
    public const PRIVACY_MARKER_EN = '## Visitor statistics';

    public const PRIVACY_MARKER_AR = '## إحصاءات الزيارات';

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> slug => [title ar, title en, body ar, body en] */
    public static function pages(): array
    {
        return [
            'cookies' => [
                'سياسة ملفات تعريف الارتباط', 'Cookie Policy',
                <<<'MD'
توضّح هذه الصفحة ملفات تعريف الارتباط (الكوكيز) وتقنيات التخزين المشابهة التي يستخدمها **دليل قطر**، ولماذا نستخدمها، وكيف يمكنك التحكم فيها.

## ما هي ملفات تعريف الارتباط؟
ملفات نصية صغيرة يحفظها المتصفح عند زيارة موقع، وتساعده على تذكّر معلومات مثل تسجيل دخولك. ونستخدم كذلك «التخزين المحلي» في المتصفح، وهو تقنية مشابهة.

## ملفات ضرورية لعمل الموقع
- **ملف الجلسة:** يُبقيك مسجّل الدخول ويحفظ اختياراتك أثناء التصفح.
- **رمز الحماية (XSRF-TOKEN):** يحمي النماذج من الطلبات المزوّرة.
لا يمكن تعطيل هذه الملفات لأن الموقع لا يعمل بدونها، ولا تُستخدم للإعلانات.

## إحصاءات الزيارات الخاصة بنا
نقيس عدد الزيارات والصفحات الأكثر قراءة بأداة خاصة بنا **لا تستخدم ملفات تعريف الارتباط**: يحفظ المتصفح رمزاً عشوائياً مجهول الهوية في التخزين المحلي (`dq_vid`) ورمزاً للجلسة الحالية (`dq_sid`). لا يحتوي الرمز على أي بيانات شخصية، ولا نخزّن عنوان IP. يمكنك حذفه في أي وقت بمسح بيانات الموقع من متصفحك.

## اختيارك لملفات تعريف الارتباط
نحفظ اختيارك (قبول أو رفض) في التخزين المحلي (`dq-consent`) حتى لا نسألك في كل زيارة.

## خدمات Google
- **Google Analytics:** قد نستخدمه لقياس الزيارات بشكل مجمّع.
- **Google AdSense:** قد نعرض إعلانات تقدّمها Google. تستخدم Google وشركاؤها ملفات تعريف الارتباط لعرض الإعلانات وقياس أدائها، وقد تُخصَّص الإعلانات بناءً على زياراتك السابقة لهذا الموقع أو لمواقع أخرى.

للزوار من المنطقة الاقتصادية الأوروبية والمملكة المتحدة وسويسرا تبقى ملفات التحليلات والإعلانات معطّلة حتى توافق عليها.

## كيف تتحكم في ملفات تعريف الارتباط
- غيّر اختيارك في أي وقت من رابط **إعدادات ملفات تعريف الارتباط** أسفل كل صفحة.
- أوقف الإعلانات المخصصة من [إعدادات الإعلانات في Google](https://adssettings.google.com) أو من [www.aboutads.info](https://www.aboutads.info/choices).
- يمكنك أيضاً حظر ملفات تعريف الارتباط أو حذفها من إعدادات المتصفح، مع العلم أن بعض أجزاء الموقع (مثل تسجيل الدخول) لن تعمل حينها.

لمزيد من التفاصيل راجع [سياسة الخصوصية](/privacy)، أو [تواصل معنا](/contact).
MD,
                <<<'MD'
This page explains the cookies and similar storage technologies **Daleel Qatar** uses, why we use them and how you can control them.

## What are cookies?
Small text files your browser keeps when you visit a website, helping it remember things such as your sign-in. We also use the browser's "local storage", a similar technology.

## Essential cookies
- **Session cookie:** keeps you signed in and remembers your choices while you browse.
- **Security token (XSRF-TOKEN):** protects forms against forged requests.
These cannot be switched off because the site does not work without them, and they are never used for advertising.

## Our own visitor statistics
We count visits and the most-read pages with our own tool, which **uses no cookies**: your browser keeps an anonymous random token in local storage (`dq_vid`) and one for the current visit (`dq_sid`). The token contains no personal information and we do not store your IP address. You can delete it at any time by clearing this site's data in your browser.

## Your cookie choice
We remember whether you accepted or declined (`dq-consent`, local storage) so we do not ask on every visit.

## Google services
- **Google Analytics:** we may use it to measure traffic in aggregate.
- **Google AdSense:** we may show ads served by Google. Google and its partners use cookies to serve and measure ads, and ads may be personalised based on your prior visits to this website or other websites.

For visitors in the European Economic Area, the United Kingdom and Switzerland, analytics and advertising storage stays off until you agree to it.

## How to control cookies
- Change your choice at any time from the **Cookie settings** link at the bottom of every page.
- Opt out of personalised advertising in [Google Ads Settings](https://adssettings.google.com) or at [www.aboutads.info](https://www.aboutads.info/choices).
- You can also block or delete cookies in your browser settings; some parts of the site, such as signing in, will then stop working.

For more details see our [Privacy Policy](/en/privacy), or [contact us](/en/contact).
MD,
            ],
            'disclaimer' => [
                'إخلاء المسؤولية', 'Disclaimer',
                <<<'MD'
يهدف **دليل قطر** إلى مساعدتك في العثور على الأعمال والخدمات في قطر. نبذل جهدنا لتكون المعلومات صحيحة ومحدّثة، لكن يُرجى مراعاة ما يلي.

## دقة المعلومات
تأتي بيانات الأعمال من أصحابها ومن مصادر عامة منشورة. قد تتغير أرقام الهواتف والعناوين وساعات العمل والأسعار دون علمنا، لذا ننصحك بالتأكد من النشاط التجاري مباشرةً قبل الزيارة أو الشراء أو الدفع.

## عدم التزكية
إدراج نشاط تجاري في الدليل أو ظهوره «مميزاً» لا يعني أننا نزكّيه أو نضمن خدماته. شارة **«موثّق»** تعني فقط أن فريقنا راجع مستندات رسمية للنشاط مثل السجل التجاري.

## التقييمات
تعبّر تقييمات المستخدمين عن آراء كاتبيها. نراجعها وفق [سياسة التحرير](/editorial-policy)، لكننا لا نتحقق من كل تجربة. وتُعرض تقييمات Google كما تنشرها Google مع نسبتها إليها.

## الإعلانات والروابط الخارجية
قد تظهر في الموقع إعلانات تقدّمها Google أو جهات أخرى، وهي لا تمثّل تزكية منّا. كما يحتوي الدليل على روابط لمواقع خارجية لا نتحكم في محتواها أو سياساتها.

## حدود المسؤولية
لا يتحمّل دليل قطر مسؤولية أي تعامل أو اتفاق بين المستخدمين والأعمال المدرجة، ولا أي خسارة تنتج عن الاعتماد على المعلومات المنشورة.

## التصحيحات
إذا وجدت معلومة غير صحيحة، استخدم زر **«الإبلاغ عن القائمة»** في صفحة النشاط أو [تواصل معنا](/contact)، وسنراجعها في أقرب وقت.
MD,
                <<<'MD'
**Daleel Qatar** exists to help you find businesses and services in Qatar. We work hard to keep information accurate and up to date, but please keep the following in mind.

## Accuracy of information
Business details come from the businesses themselves and from published public sources. Phone numbers, addresses, opening hours and prices can change without our knowledge, so please confirm with the business directly before visiting, buying or paying.

## No endorsement
A business being listed, or shown as "featured", does not mean we endorse it or guarantee its services. The **"Verified"** badge only means our team has checked official documents of the business, such as its commercial registration.

## Reviews
User reviews are the opinions of their authors. We moderate them under our [editorial policy](/en/editorial-policy), but we do not verify every experience. Google reviews are shown as Google publishes them, with attribution.

## Advertising and external links
The site may show ads served by Google or other providers; they are not endorsements. The directory also links to external websites whose content and policies we do not control.

## Limitation of liability
Daleel Qatar is not responsible for any transaction or agreement between users and listed businesses, or for any loss arising from reliance on the information published.

## Corrections
If you spot something wrong, use the **"Report listing"** button on the business page or [contact us](/en/contact) and we will review it promptly.
MD,
            ],
            'editorial' => [
                'سياسة التحرير والإدراج', 'Editorial & Listing Policy',
                <<<'MD'
نلتزم في **دليل قطر** بأن يكون كل ما ننشره مفيداً وصحيحاً ومكتوباً بعناية. توضّح هذه الصفحة كيف تُضاف القوائم وتُراجع وتُصحَّح.

## من أين تأتي القوائم
- **أصحاب الأعمال:** يضيفون نشاطهم مجاناً ويديرونه من لوحة التحكم الخاصة بهم.
- **مصادر عامة:** أدلة تجارية منشورة وبيانات متاحة للجميع. نأخذ منها **الحقائق فقط** (الاسم والهاتف والبريد والعنوان والموقع والتصنيف)، ولا ننسخ نصوصها أبداً.

## المراجعة قبل النشر
يراجع فريقنا القوائم الجديدة قبل نشرها، ونتحقق من التكرار حتى لا يظهر النشاط الواحد مرتين. تبقى القوائم الناقصة جداً بعيدة عن نتائج محركات البحث حتى تُستكمل بياناتها.

## الأوصاف والكتابة بمساعدة الذكاء الاصطناعي
تُكتب أوصاف الأعمال بكلماتنا الخاصة اعتماداً على الحقائق المتاحة فقط. قد نستعين بالذكاء الاصطناعي لكتابة مسودة الوصف أو ترجمته، لكن **لا يُنشر أي نص قبل أن يراجعه محرر** ويوافق عليه. لا نختلق خدمات أو أسعاراً أو تقييمات أو ساعات عمل.

## شارة التوثيق
تُمنح شارة **«موثّق»** فقط بعد مراجعة مستندات رسمية للنشاط مثل السجل التجاري، ويمكن سحبها إذا تبيّن خلاف ذلك.

## التقييمات
يجب أن تعكس التقييمات تجربة حقيقية. نراجع التقييمات قبل نشرها، ونرفض المسيء أو المضلل أو المدفوع منها، ولا نحذف تقييماً لأنه سلبي فقط.

## الإعلانات والمحتوى
يبقى المحتوى التحريري مستقلاً عن الإعلانات. تُعرض الإعلانات فقط في صفحات المحتوى العامة، ولا تظهر في صفحات الحساب أو النماذج أو صفحات الأخطاء، ولا تؤثر على ترتيب نتائج البحث.

## التصحيح والحذف
يمكن لأي نشاط تجاري طلب تصحيح بياناته أو حذفها، أو المطالبة بملكية قائمته لإدارتها بنفسه. استخدم زر **«الإبلاغ عن القائمة»** في صفحة النشاط أو [تواصل معنا](/contact).
MD,
                <<<'MD'
At **Daleel Qatar** we are committed to publishing content that is useful, accurate and carefully written. This page explains how listings are added, reviewed and corrected.

## Where listings come from
- **Business owners** add their business for free and manage it from their own dashboard.
- **Public sources:** published business directories and openly available data. From these we take **facts only** (name, phone, e-mail, address, location, category) and never copy their text.

## Review before publication
Our team reviews new listings before they are published and checks for duplicates, so the same business never appears twice. Listings with very little information are kept out of search-engine results until they are completed.

## Descriptions and AI-assisted writing
Business descriptions are written in our own words, using only the facts available. We may use AI to draft or translate a description, but **nothing is published until an editor has reviewed and approved it**. We never invent services, prices, ratings or opening hours.

## The verification badge
The **"Verified"** badge is granted only after we have checked official documents of the business, such as its commercial registration, and it can be withdrawn if they turn out to be wrong.

## Reviews
Reviews must reflect a genuine experience. We moderate reviews before publication and reject abusive, misleading or paid ones — but we never remove a review just because it is negative.

## Advertising and content
Our editorial content is independent of advertising. Ads are only shown on public content pages — never on account pages, forms or error pages — and they never influence the order of search results.

## Corrections and removals
Any business can ask us to correct or remove its details, or claim its listing to manage it directly. Use the **"Report listing"** button on the business page or [contact us](/en/contact).
MD,
            ],
            'advertise' => [
                'أعلن معنا', 'Advertise with Us',
                <<<'MD'
يزور **دليل قطر** أشخاص يبحثون فعلاً عن خدمات في قطر — وهي أفضل لحظة ليتعرفوا على نشاطك.

## إدراج مجاني لكل الأعمال
[أضف نشاطك](/add-your-business) مجاناً، أو اطلب ملكية قائمتك الموجودة لتدير بياناتك وصورك وساعات عملك بنفسك.

## العروض والخصومات
بعد اعتماد قائمتك يمكنك نشر عروضك من لوحة التحكم، فتظهر في صفحة النشاط وفي صفحة [العروض](/offers)، وتختفي تلقائياً عند انتهائها.

## الظهور المميز
يمكن إبراز نشاطك ضمن **الأعمال المميزة** في الصفحة الرئيسية وفي مقدّمة نتائج التصنيفات لفترة محددة، مع الإشارة إليه بوضوح على أنه «مميز».

## الحملات والشراكات
للحملات الخاصة أو الشراكات أو المحتوى المشترك، [تواصل معنا](/contact) وأخبرنا بنشاطك وأهدافك، وسيعود إليك فريقنا بخيارات مناسبة.

> تبقى نتائج البحث والتقييمات مستقلة عن الإعلانات دائماً، وكل محتوى مدفوع يُشار إليه بوضوح.
MD,
                <<<'MD'
People visit **Daleel Qatar** when they are actively looking for services in Qatar — the best moment for them to discover your business.

## Free listings for every business
[Add your business](/en/add-your-business) for free, or claim your existing listing to manage your details, photos and opening hours yourself.

## Offers and promotions
Once your listing is approved you can publish offers from your dashboard. They appear on your business page and on the [Offers](/en/offers) page, and disappear automatically when they expire.

## Featured placement
Your business can be highlighted among the **featured businesses** on the home page and at the top of category results for a set period, always clearly labelled as "Featured".

## Campaigns and partnerships
For campaigns, partnerships or sponsored content, [contact us](/en/contact) with a few words about your business and your goals, and our team will come back to you with suitable options.

> Search results and reviews always stay independent of advertising, and paid content is always clearly labelled.
MD,
            ],
        ];
    }

    /** @return array{0: string, 1: string} [ar, en] section appended to the privacy policy */
    public static function privacySection(): array
    {
        return [
            <<<'MD'
## إحصاءات الزيارات
نقيس الزيارات بأداة خاصة بنا لا تستخدم ملفات تعريف الارتباط: يحفظ المتصفح رمزاً عشوائياً مجهول الهوية في التخزين المحلي، ونسجّل الصفحة التي زرتها ونوع الجهاز والمتصفح والدولة والمدينة التقريبية (من موقع الشبكة) والموقع الذي جئت منه. **لا نخزّن عنوان IP ولا أي بيانات تعرّف بك**، ولا نستخدم هذه البيانات للإعلانات. نحتفظ بها 13 شهراً كحد أقصى، ونعرض منها أرقاماً مجمّعة فقط مثل عدد مشاهدات الصفحة. التفاصيل في [سياسة ملفات تعريف الارتباط](/cookies).
MD,
            <<<'MD'
## Visitor statistics
We measure visits with our own cookie-free tool: your browser keeps an anonymous random token in local storage, and we record the page you visited, your device type, browser, country and approximate city (from your network location) and the site you came from. **We do not store your IP address or anything that identifies you**, and this data is never used for advertising. It is kept for at most 13 months and only shown as totals, such as a page's view count. Details are in our [Cookie Policy](/en/cookies).
MD,
        ];
    }
}
