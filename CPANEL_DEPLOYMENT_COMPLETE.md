# 🚀 ER&POS - cPanel Deployment Guide

আপনার ERP/POS সিস্টেমটি এখন লাইভ সার্ভার (cPanel)-এ হোস্ট করার জন্য **১০০% প্রস্তুত**। আমরা সিস্টেমে যেসব আপডেট করেছি (ডোমেইন লক, অটো-ফোল্ডার ক্রিয়েশন, কারেন্সি সিম্বল ফিক্স, মেমরি সেফ ব্যাকআপ), তার জন্য লাইভ সার্ভারে কোনো Fatal Error বা Database Warning আসবে না।

নিচের ধাপগুলো অনুসরণ করে খুব সহজেই প্রজেক্টটি cPanel-এ আপলোড করতে পারবেন:

---

## 📂 ধাপ ১: প্রজেক্ট জিপ (ZIP) করা

1. আপনার কম্পিউটারে `c:\xampp\htdocs\erppos` ফোল্ডারে প্রবেশ করুন।
2. ফোল্ডারের ভেতরের **সবগুলো ফাইল এবং ফোল্ডার** সিলেক্ট করুন (`Ctrl + A`)।
3. রাইট-ক্লিক করে **"Compress to ZIP file"** অথবা "Add to archive..." অপশন ব্যবহার করে একটি `.zip` ফাইল তৈরি করুন (যেমন: `erppos-live.zip`)।
   > **Note:** মনে রাখবেন, `erppos` ফোল্ডারটি সরাসরি জিপ না করে, তার ভেতরের ফাইলগুলোকে জিপ করবেন।

---

## ☁️ ধাপ ২: cPanel-এ ফাইল আপলোড

1. আপনার cPanel-এ লগইন করুন (যেমন: `yourdomain.com/cpanel`)।
2. **File Manager** এ যান।
3. যদি মেইন ডোমেইনে অ্যাড করতে চান, তবে `public_html` ফোল্ডারে প্রবেশ করুন। আর যদি সাব-ডোমেইনে (যেমন: `pos.yourdomain.com`) অ্যাড করতে চান, তবে সেই সাব-ডোমেইনের ফোল্ডারে প্রবেশ করুন।
4. `Upload` বাটনে ক্লিক করে আগের ধাপে তৈরি করা `.zip` ফাইলটি আপলোড করুন।
5. আপলোড শেষ হলে File Manager-এ ফিরে আসুন, জিপ ফাইলটির উপর রাইট-ক্লিক করে **"Extract"** করুন।
6. Extract হয়ে গেলে অরিজিনাল জিপ ফাইলটি ডিলিট করে দিন।

---

## 🗄️ ধাপ ৩: ডাটাবেস তৈরি ও ইম্পোর্ট 

1. cPanel থেকে **MySQL® Database Wizard** অথবা **MySQL® Databases** এ যান।
2. একটি নতুন ডাটাবেস তৈরি করুন (যেমন: `yourcpaneluser_posdb`)।
3. একজন নতুন Database User তৈরি করুন এবং একটি শক্ত পাসওয়ার্ড দিন।
4. User-টিকে Database-এর সাথে যুক্ত (Add) করুন এবং **"All Privileges"** দিয়ে সেভ করুন।
   > **পাসওয়ার্ড, ডাটাবেস নেম এবং ইউজারনেম সেভ করে রাখুন। এগুলো একটু পরে লাগবে।**
5. cPanel এর হোমপেজে ফিরে **phpMyAdmin** এ ক্লিক করুন।
6. বামপাশ থেকে আপনার সদ্য তৈরি করা ডাটাবেসটি সিলেক্ট করুন।
7. উপরের মেনু থেকে **"Import"** এ ক্লিক করুন।
8. `Choose File` এ ক্লিক করে আপনার কম্পিউটারে থাকা `business_pos.sql` ফাইলটি দেখিয়ে দিন।
9. নিচে থাকা `Go` বা `Import` বাটনে ক্লিক করে ইম্পোর্ট শেষ করুন।

---

## ⚙️ ধাপ ৪: Config.php কানেকশন কনফিগার করা

1. File Manager থেকে `config/config.php` ফাইলটিতে রাইট-ক্লিক করে **"Edit"** এ ক্লিক করুন।
2. ফাইলটির প্রায় ২৮-৪০ নম্বর লাইনের মধ্যে **DATABASE CREDENTIALS** সেকশনটি খুঁজুন।
3. XAMPP এর সেটিংসে ডাবল-স্ল্যাশ `//` দিয়ে কমেন্ট করে দিন এবং cPanel এর সেটিংস থেকে `//` সরিয়ে আপনার ডাটাবেস ইনফরমেশন দিন।

**পরিবর্তনের আগের অবস্থা:**
```php
// XAMPP Settings (Development)
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'business_pos');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// For cPanel deployment, update the above values:
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'your_cpanel_dbname');
// define('DB_USER', 'your_cpanel_dbuser');
// define('DB_PASS', 'your_cpanel_dbpass');
```

**পরিবর্তনের পরের অবস্থা (cPanel এর জন্য):**
```php
// XAMPP Settings (Development)
// if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
// if (!defined('DB_NAME')) define('DB_NAME', 'business_pos');
// if (!defined('DB_USER')) define('DB_USER', 'root');
// if (!defined('DB_PASS')) define('DB_PASS', '');
// if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// For cPanel deployment, update the above values:
define('DB_HOST', 'localhost');
define('DB_NAME', 'আপনার_cPanel_ডাটাবেস_নাম');
define('DB_USER', 'আপনার_cPanel_ডাটাবেস_ইউজার');
define('DB_PASS', 'আপনার_ডাটাবেস_পাসওয়ার্ড');
define('DB_CHARSET', 'utf8mb4');
```

4. এরপর উপরের ডানদিক থেকে **"Save Changes"** এ ক্লিক করুন।

---

## 🔒 ধাপ ৫: ডোমেইন লক আপডেট করা (ঐচ্ছিক কিন্তু সতর্কতার জন্য)

1. একই `config/config.php` ফাইলের নিচের দিকে (প্রায় ১৪০-১৫০ নম্বর লাইনের পর) `ALLOWED_DOMAINS` অ্যারেটি খুঁজুন।
2. সেখানে আপনার লাইভ ডোমেইনের নামটি যোগ করা আছে কি না নিশ্চিত করুন:
```php
define('ALLOWED_DOMAINS', [
    'localhost',
    '127.0.0.1',
    'p.cleansbuy.com' // আপনার লাইভ ডোমেইন 
]);
```
3. আপনি চাইলে `localhost` এবং `127.0.0.1` ডিলিট করে দিতে পারেন যাতে শুধুমাত্র ডোমেইন থেকেই সিস্টেমটি চলে।

---

## 🎯 সম্পূর্ণ!
এখন আপনার ব্রাউজারে গিয়ে ডোমেইনটি ভিজিট করুন। সিস্টেমটি সফলভাবে লাইভ হয়ে যাবে!

**লগইন ক্রেডেনশিয়াল:**
- **Email:** admin@admin.com
- **Password:** 12345678

*(লগইন করার পর অবশ্যই অ্যাডমিন পাসওয়ার্ড পরিবর্তন করে নেবেন!)*
