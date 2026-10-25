# COPOT PROJECT REGISTRY
Date version: 2026-10-25 22:45:37 WIB

## 1. Variabel

- `Primary Runtime`
  Description: Default runtime environment untuk development, debugging, integration, dan validation normal.
  Value: **XAMPP**

## 2. Primary Runtime Rules

`Primary Runtime` menunjuk ke environment XAMPP.

`Primary Runtime` adalah environment category/provider, bukan instance runtime tertentu dan bukan installation identity.

Nilai ini tidak menentukan:

- runtime identity;
- endpoint;
- port;
- APP_ROOT;
- PUBLIC_ROOT;
- database name;
- installation identity;
- runtime state;
- authorization untuk menjalankan atau mengubah runtime.

### Isolated Runtime XAMPP Convention

Jika task membutuhkan isolated runtime berbasis `Primary Runtime`, gunakan naming convention berikut:

- APP_ROOT: `C:\xampp\copot-sdr[RuntimeName]`
- PUBLIC_ROOT: `C:\xampp\htdocs[RuntimeName]`
- Database Name: `copot_sdr_[RuntimeName]`

`[RuntimeName]` harus diganti dengan nama runtime yang konsisten, unik, dan dicatat bersama endpoint, port, serta state runtime yang relevan.

Placeholder tersebut bukan bagian literal dari path atau nama database.

Konvensi ini hanya menentukan lokasi dan naming isolated runtime. Konvensi ini tidak memberikan authorization untuk membuat, mengubah, menghapus, atau menjalankan runtime maupun database.
