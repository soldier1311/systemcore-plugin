# 24Ounce Core – Architecture Reference

هذا الملف هو المرجع الوحيد المعتمد لفهم وبناء وتعديل إضافة 24ounce-core.
أي كود جديد يجب أن يلتزم بما ورد هنا حرفيًا.

---

## 1. الهيكل العام

24ounce-core.php
├─ admin/
│  ├─ menu.php                (تعريف قوائم لوحة التحكم)
│  ├─ page-prices.php         (واجهة إدارة الأسعار فقط – بدون منطق)
│  ├─ save-handler.php        (استقبال وحفظ البيانات من admin)
│  ├─ ajax-trading.php        (Ajax خاص بحالة التداول)
│
├─ includes/
│  ├─ database.php            (جميع عمليات SQL – المصدر الوحيد)
│  ├─ registry.php            (ثوابت – جداول – مسارات – Hooks)
│
│  ├─ price-engine.php        (منطق الحساب الأساسي للأسعار)
│  ├─ price-service.php       (تنسيق الأعمال – يستدعي engine + database)
│  ├─ price-admin-service.php (منطق خاص بالإدارة فقط)
│  ├─ price-ajax.php          (Ajax للأسعار – لا حسابات مباشرة)
│  ├─ price-shortcode.php     (عرض فقط)
│
│  ├─ chart-engine.php        (منطق بيانات الرسوم)
│  ├─ chart-ajax.php          (Ajax للرسوم)
│
│  ├─ wallet-engine.php       (محفظة المستخدم)
│  ├─ vault-ledger-engine.php (سجل الحركات)
│  ├─ order-engine.php        (أوامر البيع والشراء)
│
├─ assets/
│  ├─ js/                     (JavaScript فقط)
│  └─ css/                    (CSS فقط)
│
├─ templates/
│  └─ gold-chart.php          (قوالب عرض فقط)

---

## 2. القواعد الذهبية (ممنوع كسرها)

1. لا يوجد SQL خارج database.php
2. لا يوجد حساب أو منطق داخل admin
3. Ajax لا يحسب – فقط يستدعي Service
4. Engine لا يعرف شيئًا عن Admin أو Ajax
5. أسماء الدوال والكلاسات تؤخذ من registry.php فقط
6. أي ملف جديد = تحديث ARCHITECTURE.md أولًا

---

## 3. تدفق البيانات الرسمي

Admin / Ajax / Shortcode
        ↓
   Service Layer
        ↓
     Engine
        ↓
     Database

---

## 4. سبب وجود هذا الملف

- منع تكرار المنطق
- منع نسيان المسارات
- منع اختراع دوال جديدة
- جعل ChatGPT أو أي مطور يعمل بدون تخمين

هذا الملف ملزم.
