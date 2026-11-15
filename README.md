# 🏫 Bridges Note - MINEDU ESCALE Bridge API

Microservicio y API REST en **Laravel 13 / PHP 8.3+** diseñado para sincronizar, estructurar y servir el catálogo oficial de instituciones educativas (colegios) y la división política/geográfica (regiones, provincias y distritos) del Perú, consumiendo directamente la API pública oficial de **MINEDU / ESCALE** (*Estadística de la Calidad Educativa*).

---

## 📋 Tabla de Contenidos

1. [Propósito del Proyecto](#-propósito-del-proyecto)
2. [Arquitectura y Flujo de Datos](#-arquitectura-y-flujo-de-datos)
3. [Requisitos del Sistema](#-requisitos-del-sistema)
4. [Instalación y Puesta en Marcha](#-instalación-y-puesta-en-marcha)
5. [Configuración de Entorno (.env)](#-configuración-de-entorno-env)
6. [Sincronización de Datos (Consola Artisan)](#-sincronización-de-datos-consola-artisan)
7. [Documentación de la API REST](#-documentación-de-la-api-rest)
8. [Estructura del Proyecto](#-estructura-del-proyecto)
9. [Pruebas Automatizadas](#-pruebas-automatizadas)
10. [Solución de Problemas Frecuentes](#-solución-de-problemas-frecuentes)
11. [Migración desde Laravel 7](#-migración-desde-laravel-7)

---

## 🎯 Propósito del Proyecto

Servir como un **puente de datos optimizado** para aplicaciones cliente (sistemas de admisión universitaria, plataformas de matrícula escolar, intranets, etc.), permitiéndoles autocompletar ubigeos y consultar colegios mediante consultas locales ultrarrápidas, sin depender de la latencia o disponibilidad de los servidores estatales.

---

## 🔄 Arquitectura y Flujo de Datos

```
[ API MINEDU / ESCALE ] 
 (escale.minedu.gob.pe)
          │
          ▼  (Comando: php artisan sync:locations)
[ Base de Datos Local MySQL (escalebridge) ]
  ├── regions
  ├── provinces
  ├── districts
  └── schools (con ubigeo / idDistrito)
          │
          ▼  (LocationController)
[ Endpoints API REST Local / Producción ]
  ├── GET /api/regiones
  ├── GET /api/provincias/{idRegion}
  ├── GET /api/distritos/{idProvince}
  └── GET /api/colegios/{ubigeo}
          │
          ▼
[ Clientes / Aplicaciones Externas ] (ej. admisionweb)
```

---

## 💻 Requisitos del Sistema

- **PHP:** `8.3` o superior (probado también con `8.4`). Extensiones: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `intl` (y `pdo_sqlite` para las pruebas).
  > `composer.json` fija la plataforma en PHP 8.3 (`config.platform.php`), así las dependencias se mantienen compatibles con 8.3 aunque desarrolles con una versión más nueva.
- **Laravel Framework:** `13.x`
- **Gestor de Paquetes:** Composer 2.x
- **Base de Datos:** MySQL 8.0+ o MariaDB 10.6+
- **Servidor Web Local:** `php artisan serve`, Laravel Herd o Laragon. El *document root* es la carpeta `public/`.

---

## 🚀 Instalación y Puesta en Marcha

### 1. Clonar el repositorio
```bash
git clone https://github.com/hubelsolis/brigesnote.git
cd brigesnote
```

### 2. Instalar dependencias de Composer
```bash
composer install
```

### 3. Configurar variables de entorno
Copia el archivo de ejemplo y genera la clave de cifrado:
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Crear la Base de Datos
Crea una base de datos vacía en tu gestor MySQL (ej. HeidiSQL o phpMyAdmin):
- **Nombre:** `escalebridge`
- **Cotejamiento:** `utf8mb4_unicode_ci`

### 5. Ejecutar las Migraciones
Crea las tablas en la base de datos:
```bash
php artisan migrate
```

### 6. Sincronizar el catálogo escolar
Descarga la información oficial desde MINEDU:
```bash
php artisan sync:locations
```

### 7. Iniciar el servidor
```bash
php artisan serve
```
La aplicación quedará disponible en `http://127.0.0.1:8000`.

---

## ⚙️ Configuración de Entorno (.env)

`.env.example` contiene las variables del curso. Las más importantes:

```env
APP_NAME='PROYECTO BASE'
LOGO_PROJECT='https://cdn-icons-png.flaticon.com/512/6886/6886480.png'
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost

# Conexión MySQL (pon tu contraseña real en DB_PASSWORD)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=escalebridge
DB_USERNAME=root
DB_PASSWORD=

# Drivers en archivo / síncronos: no requieren tablas extra en la BD
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Opcionales: sincronización con ESCALE
# ESCALE_BASE_URL=https://escale.minedu.gob.pe/padron/rest
# ESCALE_TIMEOUT=30
# ESCALE_RETRIES=5
# ESCALE_PAGE_SIZE=500
```

> Laravel 11+ renombró `CACHE_DRIVER` → `CACHE_STORE` y `BROADCAST_DRIVER` → `BROADCAST_CONNECTION`. `config/cache.php` acepta ambos nombres. `LOGO_PROJECT` se expone como `config('app.logo')`. Las variables `MIX_*`, `PUSHER_*` y `BROADCAST_DRIVER` se conservan por compatibilidad con el curso, pero esta API no las usa.

---

## 📥 Sincronización de Datos (Consola Artisan)

El comando central del sistema es:

```bash
php artisan sync:locations
```

### ¿Cómo opera la sincronización?
1. **Regiones (`/padron/rest/regiones`):** Descarga los 25 departamentos del país con sus nombres, coordenadas (`pointX`, `pointY`) y nivel de `zoom`.
2. **Provincias (`/padron/rest/provincias?idRegion={id}`):** Por cada región, descarga sus provincias.
3. **Distritos (`/padron/rest/distritos?idProvincia={id}`):** Por cada provincia, descarga los distritos correspondientes.
4. **Colegios (`/padron/rest/instituciones?estados=1&ubigeo={idDistrito}&start={n}&max=500`):** Por cada distrito, descarga **página por página** (ESCALE devuelve solo 50 registros si no se pagina) las instituciones educativas activas y las almacena en la tabla `schools` vinculando el código modular (`codMod`) como llave primaria y asociando el `idDistrito`.

> ⏱️ **Nota:** La sincronización total recorre más de 1,800 distritos y 80,000 colegios, por lo que puede tomar varios minutos según la velocidad de conexión y respuesta del servidor del MINEDU.

### Comportamiento ante errores
- Cada llamada a ESCALE tiene *timeout* (`ESCALE_TIMEOUT`, 30 s) y reintentos con espera creciente (`ESCALE_RETRIES`, 5).
- Si falla una región, provincia o distrito, se muestra el error y **la sincronización continúa** con los demás.
- El comando termina con código `0` si todo salió bien y `1` si hubo algún error de comunicación. Puedes volver a ejecutarlo sin riesgo: usa `updateOrCreate`, así que no duplica registros.
- Un distrito sin colegios activos muestra `No se encontraron registros de escuelas`, pero no cuenta como error.

---

## 📡 Documentación de la API REST

Todos los endpoints retornan respuestas en formato JSON bajo el prefijo `/api/`.

- **CORS:** abierto a cualquier origen (`config/cors.php`).
- **Límite:** 60 peticiones por minuto por IP. Al superarlo se responde `429 Too Many Requests`.
- **Tipos:** `pointX` y `pointY` se devuelven como texto con 7 decimales (precisión exacta de `DECIMAL(10,7)`), y `zoom` como entero. Cada registro incluye además `created_at` y `updated_at`.
- **Errores:** si no hay resultados, se responde `404` con `{"status": "error", "message": "..."}`. Cualquier otro error bajo `/api` también se responde en JSON.
- `GET /` devuelve una descripción breve de la API y `GET /up` sirve como chequeo de salud.

### 1. Listar Regiones / Departamentos
Obtiene todas las regiones del país.

- **URL:** `/api/regiones`
- **Método:** `GET`
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idRegion": "15",
      "nombreRegion": "LIMA",
      "pointX": "-12.0463740",
      "pointY": "-77.0427930",
      "zoom": 10
    }
  ]
}
```

---

### 2. Listar Provincias por Región
Obtiene las provincias pertenecientes a una región (mediante los 2 primeros dígitos del código de departamento).

- **URL:** `/api/provincias/{idRegion}`
- **Método:** `GET`
- **Ejemplo:** `/api/provincias/15` (Provincias de Lima)
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idProvincia": "1501",
      "nombreProvincia": "LIMA",
      "pointX": "-12.0463740",
      "pointY": "-77.0427930"
    }
  ]
}
```

---

### 3. Listar Distritos por Provincia
Obtiene los distritos de una provincia (mediante los 4 dígitos de ubigeo provincial).

- **URL:** `/api/distritos/{idProvince}`
- **Método:** `GET`
- **Ejemplo:** `/api/distritos/1501` (Distritos de la provincia de Lima)
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idDistrito": "150101",
      "nombreDistrito": "LIMA"
    },
    {
      "idDistrito": "150102",
      "nombreDistrito": "ANCON"
    }
  ]
}
```

---

### 4. Listar Colegios por Distrito (Ubigeo)
Obtiene todas las instituciones educativas registradas en un distrito según su ubigeo de 6 dígitos.

- **URL:** `/api/colegios/{ubigeo}`
- **Método:** `GET`
- **Ejemplo:** `/api/colegios/150101`
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "codMod": "0273572",
      "idDistrito": "150101",
      "nombreCenEdu": "JUAN JIMENEZ PIMENTEL",
      "cenPob": "LIMA",
      "dirCen": "JR. LAMPA 240",
      "director": "PEDRO PEREZ",
      "dis_crecer": null,
      "dis_juntos": null
    }
  ]
}
```

---

## 📂 Estructura del Proyecto

```
brigesnote/
├── app/
│   ├── Console/Commands/
│   │   └── SyncLocations.php       # Comando 'sync:locations' (consumo de API ESCALE)
│   ├── Http/Controllers/
│   │   └── LocationController.php  # Controlador de endpoints /api/
│   ├── Models/
│   │   ├── District.php            # Modelo Distritos
│   │   ├── Province.php            # Modelo Provincias
│   │   ├── Region.php              # Modelo Regiones
│   │   ├── School.php              # Modelo Colegios / Escuelas
│   │   └── User.php                # Modelo de usuario del framework
│   └── Providers/
│       └── AppServiceProvider.php  # Límite de 60 peticiones/minuto
├── bootstrap/app.php               # Rutas, middleware y manejo de errores
├── config/
│   ├── cors.php                    # CORS abierto para /api/*
│   └── services.php                # Configuración de ESCALE
├── database/
│   ├── migrations/                 # Tablas (mismos nombres de archivo que en Laravel 7)
│   └── seeders/DatabaseSeeder.php
├── public/                         # Document root (index.php, .htaccess)
├── routes/
│   ├── api.php                     # Definición de rutas REST
│   ├── web.php                     # GET / (descripción de la API)
│   └── console.php
├── tests/Feature/                  # Pruebas de la API y de la sincronización
├── .env.example                    # Plantilla de variables de entorno
└── .gitignore                      # Reglas de exclusión para Git
```

---

## 🧪 Pruebas Automatizadas

```bash
php artisan test
```

Las pruebas usan SQLite en memoria (ver `phpunit.xml`), así que no tocan la base MySQL, y simulan la API de MINEDU con `Http::fake()`, sin hacer llamadas reales. Cubren:
- los 4 endpoints (éxito, 404, tipos del JSON, CORS y límite de peticiones);
- la sincronización: respuestas con un objeto o con una lista, campos faltantes, distritos sin colegios, errores HTTP y re-ejecución sin duplicados.

Estilo de código: `vendor/bin/pint`.

---

## 🛠️ Solución de Problemas Frecuentes

### 1. Error: `SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'`
- **Causa:** El usuario de MySQL tiene contraseña y `DB_PASSWORD` está vacío en `.env`.
- **Solución:** Coloca la contraseña correcta en `DB_PASSWORD` y ejecuta `php artisan config:clear`.

### 2. Error: `Base table or view not found: 1146 Table '...sessions' (o 'cache') doesn't exist`
- **Causa:** `SESSION_DRIVER=database` o `CACHE_DRIVER=database` sin las tablas correspondientes.
- **Solución:** Usa `SESSION_DRIVER=file` y `CACHE_DRIVER=file`, como en `.env.example`.

### 3. Error: `Your requirements could not be resolved` / `php >=8.3` al hacer `composer install`
- **Causa:** Composer se está ejecutando con PHP 7.x u 8.0–8.2.
- **Solución:** Instala o selecciona PHP 8.3+ (en Herd o Laragon) y verifica con `php -v`.

### 4. La página muestra el listado de archivos o da 404 en todas las rutas
- **Causa:** El servidor web apunta a la raíz del proyecto en lugar de `public/`.
- **Solución:** Configura el *document root* en `public/` o usa `php artisan serve`.

---

## 🔁 Migración desde Laravel 7

Cambios principales respecto a la versión anterior (Laravel 7.30 / PHP 7.4):

| Antes (Laravel 7) | Ahora (Laravel 13) |
|---|---|
| `index.php`, `.htaccess` en la raíz | Carpeta estándar `public/` |
| `app/Http/Kernel.php`, `app/Console/Kernel.php`, `RouteServiceProvider` | `bootstrap/app.php` |
| `fideloper/proxy`, `fruitcake/laravel-cors` | Middleware incluido en el framework |
| `facade/ignition`, PHPUnit 9 | Página de errores nativa, PHPUnit 12 |
| Migraciones con nombre de clase | Clases anónimas (mismos nombres de archivo y mismo esquema) |
| `database/seeds/` | `database/seeders/` con namespace |
| Frontend con laravel-mix (sin uso) | Eliminado: el proyecto es solo API |
| `GET /api/user` (`auth:api`, nunca funcional: no existía la columna `api_token`) | Eliminado |
| `zoom` llegaba como `"7"` (texto) | `zoom` llega como `7` (entero) |

Una base `escalebridge` creada con la versión anterior sigue siendo compatible: `php artisan migrate` no tiene nada pendiente.

---

## 📄 Licencia

Este proyecto es de código abierto bajo la licencia [MIT](LICENSE).
