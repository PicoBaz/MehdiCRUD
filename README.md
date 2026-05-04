
# این کامند برای پروژه های شخصی خودم بوده ، پابلیکش کردم شاید واسه دوستان کاربردی باشه 

**[🇬🇧 English](#-english-guide) | [🇮🇷 فارسی](#-راهنمای-فارسی)**

---

## 🌟 English Guide

### What Does It Do?

This command generates complete CRUD operations from your migrations in seconds:

✅ **FormRequest Classes** - With smart validation rules
✅ **Query Class** - For complex database queries  
✅ **RESTful Controller** - With all CRUD methods
✅ **Zero Configuration** - Works out of the box

### Installation

1. Copy `MehdiCrudCommand.php` to `app/Console/Commands/`
2. Copy `CacheKeyGenerator.php` to `app/Support/Cache`
3. Copy `CacheService.php` to `app/Support/Cache`
4. Copy `HasListCacheInvalidation.php` to `app/Traits`
5. Use `HasListCacheInvalidation` in your Models
6. That's it! Laravel auto-discovers commands.

### Quick Start

**For non-modular projects:**
```bash
php artisan mehdi:crud Shop
```

**For modular projects:**
```bash
php artisan mehdi:crud Shop RepairShop
```

### Examples

#### Your Migration:
```php
Schema::create('shops', function (Blueprint $table) {
    $table->id();
    $table->string('name', 50);
    $table->string('code', 50)->unique();
    $table->enum('is_active', ['active', 'inactive']);
    $table->decimal('fee', 8, 2)->nullable();
    $table->timestamps();
});
```

#### Generated StoreRequest:
```php
return [
    'name' => 'required|string',
    'code' => 'required|string|unique:shops,code',
    'is_active' => 'nullable|in:active,inactive',
    'fee' => 'nullable|numeric',
];
```

### Supported Column Types

| Type | Rule |
|------|------|
| `string`, `varchar` | `string` |
| `integer`, `bigInteger` | `integer` |
| `decimal`, `float` | `numeric` |
| `enum` | `in:value1,value2` |
| `date`, `dateTime` | `date`, `date_format` |
| `unique()` | `unique:table,column` |

### Requirements

- **Laravel** 12.x+
- **PHP** 8.3+
- **nwidart/laravel-modules** 12.x+ (only for modular projects)

### Modular Projects ⚠️

If using `nwidart/laravel-modules`, install it first:
```bash
composer require nwidart/laravel-modules:^12
php artisan module:publish-config
```

**Without this package, modular commands won't work!**

---

## 🔷 راهنمای فارسی

### این دستور چی کار می‌کند؟

عملیات CRUD کامل را در چند ثانیه تولید می‌کند:

✅ **FormRequest کلاس‌ها** - با validation rules هوشمندانه
✅ **Query Class** - برای query‌های پیچیده  
✅ **RESTful Controller** - با تمام method‌های CRUD
✅ **بدون تنظیمات** - از صندوق خالی کار می‌کند

### نصب

1. فایل `MehdiCrudCommand.php` را در `app/Console/Commands/` قرار دهید
2. تمام! Laravel به صورت خودکار command را شناسایی می‌کند.

### شروع سریع

**برای پروژه غیر ماژولار:**
```bash
php artisan mehdi:crud Shop
```

**برای پروژه ماژولار:**
```bash
php artisan mehdi:crud Shop RepairShop
```

### مثال‌ها

#### Migration شما:
```php
Schema::create('shops', function (Blueprint $table) {
    $table->id();
    $table->string('name', 50);
    $table->string('code', 50)->unique();
    $table->enum('is_active', ['active', 'inactive']);
    $table->decimal('fee', 8, 2)->nullable();
    $table->timestamps();
});
```

#### StoreRequest تولید شده:
```php
return [
    'name' => 'required|string',
    'code' => 'required|string|unique:shops,code',
    'is_active' => 'nullable|in:active,inactive',
    'fee' => 'nullable|numeric',
];
```

### نوع‌های Column پشتیبانی شده

| نوع | Rule |
|------|------|
| `string`, `varchar` | `string` |
| `integer`, `bigInteger` | `integer` |
| `decimal`, `float` | `numeric` |
| `enum` | `in:value1,value2` |
| `date`, `dateTime` | `date`, `date_format` |
| `unique()` | `unique:table,column` |

### نیازمندی‌ها

- **Laravel** 12.x+
- **PHP** 8.3+
- **nwidart/laravel-modules** 12.x+ (فقط برای پروژه‌های ماژولار)

### پروژه‌های ماژولار ⚠️

اگر از `nwidart/laravel-modules` استفاده می‌کنید، ابتدا نصب کنید:
```bash
composer require nwidart/laravel-modules:^12
php artisan module:publish-config
```

**بدون این پکیج، دستورات ماژولار کار نخواهند کرد!**

