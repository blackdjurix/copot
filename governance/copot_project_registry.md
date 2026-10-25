# COPOT PROJECT REGISTRY
Date version: 2026-10-25 23:34:28 WIB

## 1. Variabel

- `Primary Runtime`
  Description: Default runtime environment untuk development, debugging, integration, dan validation normal.
  Value: **XAMPP**

## 2. Runtime model

`Primary Runtime` menunjuk ke environment XAMPP.

`Primary Runtime` adalah environment category/provider, bukan instance runtime tertentu dan bukan installation identity.

Semua runtime project COPOT yang didefinisikan di registry ini harus berbasis `Registry::Primary Runtime`. Untuk XAMPP, path project dan public root harus berada di bawah installation path XAMPP yang sedang digunakan. Jangan membuat root arbitrer di luar environment tersebut, misalnya `C:\[folder baru]`.

### Default configuration boundary

Default setting `Primary Runtime` tidak boleh diubah secara persistent tanpa authorization eksplisit.

Jika task membutuhkan capability optional dari `Primary Runtime`, misalnya mengaktifkan `zip` pada `php.ini`, perlakukan itu sebagai task-scoped runtime configuration change, bukan sebagai perubahan terhadap nilai `Primary Runtime`.

Task-scoped runtime configuration change hanya boleh dilakukan bila:

- setting dan file konfigurasi yang exact telah diidentifikasi;
- authorization mencakup perubahan konfigurasi runtime tersebut;
- perubahan dibuat sekecil mungkin dan scope-nya dicatat;
- nilai sebelum dan sesudah perubahan dicatat;
- capability divalidasi setelah perubahan;
- konfigurasi dikembalikan setelah task selesai, kecuali persistent change secara eksplisit diotorisasi.

Perubahan persistent terhadap default setting adalah action terpisah dan tidak boleh diinfer dari authorization implementation, validation, atau penggunaan feature.

## 3. Main Runtime

`Main Runtime` adalah instance runtime existing yang mencerminkan current project state untuk development, debugging, integration, validation normal, serta operational atau human acceptance bila target tersebut ditetapkan oleh workstream atau contract.

Layout canonical `Main Runtime`:

- APP_ROOT: `[Primary Runtime Path]\copot\dev`
- PUBLIC_ROOT: `[Primary Runtime Path]\htdocs\dev`
- Database Name: `copot_dev`

`[Primary Runtime Path]` adalah installation path XAMPP yang actual, misalnya `C:\xampp` bila itu installation yang sedang digunakan. Placeholder tersebut bukan bagian literal dari path.

`Main Runtime` tidak boleh diperlakukan sebagai instance disposable. Identity, endpoint, port, state aktual, dan synchronization delta tetap dicatat pada Handoff atau execution context yang relevan.

## 4. Separated Disposable Runtime (SDR)

`Separated Disposable Runtime` (`SDR`) digunakan bila isolation cukup dilakukan pada app filesystem, public filesystem, dan database namespace, sementara environment `Primary Runtime` tetap digunakan.

`SDR` wajib tetap berbasis `Registry::Primary Runtime`. Gunakan path di bawah installation path XAMPP yang sama, terutama directory `htdocs`; jangan membuat root baru di luar environment tersebut.

Layout canonical `SDR`:

- APP_ROOT: `[Primary Runtime Path]\copot\sdr\[RuntimeName]`
- PUBLIC_ROOT: `[Primary Runtime Path]\htdocs\[RuntimeName]`
- Database Name: `copot_sdr_[RuntimeName]`

`[RuntimeName]` harus diganti dengan nama runtime yang konsisten dan unik. Short commit ID adalah nilai yang disarankan, misalnya `85eb6a4c`. Jika satu commit membutuhkan lebih dari satu runtime, tambahkan suffix yang membedakan instance secara deterministic.

Placeholder tersebut bukan bagian literal dari path atau nama database.

`SDR` tidak dengan sendirinya berarti instance MariaDB/MySQL atau port service yang terpisah.

## 5. Isolated Disposable Runtime (IDR)

`Isolated Disposable Runtime` (`IDR`) digunakan hanya bila isolation harus mencakup service atau runtime instance, bukan hanya directory dan database namespace. Contohnya adalah instance MariaDB/MySQL terpisah, port service terpisah, configuration root terpisah, atau process/service state terpisah.

`IDR` tetap wajib berbasis `Registry::Primary Runtime` dan menggunakan layout berikut: APP_ROOT di bawah `[Primary Runtime Path]\copot\idr\[RuntimeName]` dan PUBLIC_ROOT di bawah `[Primary Runtime Path]\htdocs\[RuntimeName]`. Service root, port, configuration root, database name, dan endpoint wajib dicatat untuk setiap instance.

Gunakan `IDR` sebagai runtime role hanya bila service-level isolation tersebut benar-benar diperlukan. Bila isolation cukup dilakukan di directory dan database namespace dalam instance service yang sama, gunakan `SDR`.

Untuk `SDR` maupun `IDR`, runtime name sebaiknya menggunakan short commit ID yang terkait dengan source state. Runtime identity, endpoint, port, dan state bukan bagian dari variable `Primary Runtime`.

## 6. Runtime authorization boundary

Konvensi path, naming, dan runtime classification di registry ini hanya menentukan model dan layout runtime. Konvensi ini tidak memberikan authorization untuk membuat, mengubah, menghapus, menjalankan, mengaktifkan capability, atau mengubah database maupun service.

Perubahan implementation/package-owned filesystem dan database state tetap mengikuti Package Lifecycle dan authorization yang berlaku. Runtime copy maupun disposable runtime bukan authority durable untuk Repository state.