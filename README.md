<div dir="rtl">

# Simple Accounts — نظام محاسبة مفتوح المصدر

نظام محاسبة عربي بالقيد المزدوج، متعدد الشركات والفروع والعملات. مبني بـ Laravel 13 و Vue 3.

## المميزات

- **تعدد الشركات**: كل شركة معزولة ببياناتها ومستخدميها، مع لوحة مدير عام (Super Admin) لإدارة الشركات.
- **دليل حسابات شجري** مع ربط الحسابات بالعملات.
- **قيود يومية** بالقيد المزدوج: مسودة ← ترحيل ← عكس القيد (بدون حذف، لحفظ الأثر المحاسبي).
- **سندات قبض وصرف** تولّد قيودها تلقائياً عند الترحيل.
- **تعدد العملات** وأسعار الصرف لكل شركة.
- **الفروع والصناديق** وربط الحركات بها.
- **السنوات والفترات المالية** مع إقفال الفترات.
- **ترقيم تلقائي للمستندات**.
- **التقارير**: ميزان المراجعة، كشف حساب، قائمة الدخل، الميزانية العمومية، ولوحة ملخص.
- **المستخدمون والأدوار**: حالة المستخدم، آخر دخول، وسجل تسجيلات الدخول.
- **سجل تدقيق** (Audit Log) للعمليات.
- واجهة عربية RTL.

## التقنيات

| الطبقة | التقنية |
|---|---|
| Backend | PHP 8.3+، Laravel 13، Sanctum (API tokens) |
| Frontend | Vue 3، Vue Router، Pinia، Bootstrap 5، Vite |
| قاعدة البيانات | MySQL 8 (أو SQLite للتطوير والاختبارات) |
| التشغيل | Docker (nginx + php-fpm + supervisor) |

## التشغيل محلياً

</div>

```bash
git clone https://github.com/Basharshaeb/simple-accounts-oss.git
cd simple-accounts-oss
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

<div dir="rtl">

افتح `http://localhost:8000`.

### بيانات تجريبية

أمر `--seed` ينشئ شركتين تجريبيتين وحسابات وقيوداً، ومستخدمين تجريبيين. كلمات المرور موجودة في [`database/seeders/DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php).

> ⚠️ **لا تشغّل الـ seeder على بيئة الإنتاج**، أو غيّر كلمات مرور المستخدمين التجريبيين فوراً. كلمات المرور هذه منشورة للجميع.

## التشغيل بـ Docker

</div>

```bash
cp .env.production.example .env
# عدّل DB_PASSWORD و DB_ROOT_PASSWORD و APP_URL، وولّد APP_KEY:
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

<div dir="rtl">

التطبيق يستمع على `127.0.0.1:8080`. ضع أمامه reverse proxy (nginx / Caddy) مع HTTPS.

## الاختبارات

</div>

```bash
php artisan test
```

<div dir="rtl">

## المساهمة

المساهمات مرحّب بها: افتح Issue لاقتراح أو خطأ، أو أرسل Pull Request.

## الرخصة

[MIT](LICENSE)

</div>

---

**English:** Simple Accounts is an open-source, Arabic-first, double-entry accounting system (multi-company, multi-branch, multi-currency) built with Laravel 13 and Vue 3. Features: chart of accounts, journal entries with posting and reversal, receipts and payments, fiscal periods, trial balance, account statement, income statement, balance sheet, and audit log. MIT licensed.
