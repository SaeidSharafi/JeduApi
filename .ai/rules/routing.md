# Routing — Guard by File
Glob: `routes/Api/**`

- `routes/Api/V1/admin/admin.php` → `auth:staff`
- `routes/Api/V1/customer.php` → `auth:user`
- `routes/Api/V1/shop/shop.php` → no guard

Guards applied automatically per file. Never add `middleware('auth:...')` manually. Never mix guard types across files.

New admin endpoint → `admin.php`. New customer endpoint → `customer.php`. New public endpoint → `shop.php`.
