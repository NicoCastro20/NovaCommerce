# Despliegue de NovaCommerce en Microsoft Azure

Guía completa para desplegar el proyecto (Laravel 13 API + Vue 3 SPA + MySQL) en
Azure usando una cuenta gratuita.

## Arquitectura

| Componente        | Servicio de Azure                        | Plan        |
|-------------------|------------------------------------------|-------------|
| Backend (Laravel) | App Service (PHP **8.3**, Linux)         | B1 (Basic)  |
| Frontend (Vue)    | Static Web App                           | Free        |
| Base de datos     | Azure Database for MySQL Flexible Server | Burstable B1ms |

> ⚠️ **PHP 8.3, no 8.2.** El `composer.json` exige `php: ^8.3` y Laravel 13.
> Un App Service con PHP 8.2 haría fallar el `composer install` del despliegue.

---

## 1. APP_KEY generada para producción

Copia este valor en la variable `APP_KEY` del App Service:

```
APP_KEY=base64:sh0Rb40HN9XyS863CxjJ6tZCX4LBgXI7nNqHwD331Kk=
```

---

## 2. Variables de entorno del App Service

En el portal: **App Service → Settings → Environment variables → App settings**.
Añádelas una a una (botón **+ Add**) o usa **Advanced edit** para pegarlas en bloque.

Sustituye los valores marcados con `[rellenar]` por los datos reales de tu
servidor MySQL.

```
APP_NAME=NovaCommerce
APP_ENV=production
APP_KEY=base64:sh0Rb40HN9XyS863CxjJ6tZCX4LBgXI7nNqHwD331Kk=
APP_DEBUG=false
APP_URL=https://novacommerce-api.azurewebsites.net
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=novacommerce-db.mysql.database.azure.com
DB_PORT=3306
DB_DATABASE=novacommerce
DB_USERNAME=[rellenar]
DB_PASSWORD=[rellenar]
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

BROADCAST_CONNECTION=log
CACHE_STORE=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.azurewebsites.net
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none

SANCTUM_STATEFUL_DOMAINS=novacommerce.azurestaticapps.net
SPA_URL=https://novacommerce.azurestaticapps.net

SCM_DO_BUILD_DURING_DEPLOYMENT=true
```

### Notas sobre estas variables

- **`MYSQL_ATTR_SSL_CA`**: se cambió la ruta original
  (`/var/ssl/certs/ca-cert-Baltimore_CyberTrust_Root.pem`) por
  `/etc/ssl/certs/ca-certificates.crt`. El certificado Baltimore CyberTrust está
  retirado; Azure MySQL Flexible Server usa raíces DigiCert, que ya están
  incluidas en el almacén de certificados del contenedor Linux de App Service.
- **`BROADCAST_CONNECTION`**: en Laravel 11+ la variable se llama así (no
  `BROADCAST_DRIVER`, que era de Laravel ≤10).
- **`APP_LOCALE=es` / `APP_FALLBACK_LOCALE=es`**: añadidas para que los mensajes
  de validación y autenticación salgan en español en producción.
- **Sesiones cruzadas**: la SPA usa autenticación por **token Bearer**
  (`localStorage`), así que el login funciona entre dominios distintos sin
  depender de cookies. Las variables `SESSION_*` y `SANCTUM_*` se dejan
  configuradas igualmente como respaldo.

---

## 3. 🔧 Ajuste OBLIGATORIO de las URLs reales

Azure **casi nunca** entrega las URLs exactas previstas:

- Los App Service nuevos suelen recibir un sufijo aleatorio:
  `novacommerce-api-a1b2c3.azurewebsites.net`.
- Las Static Web Apps reciben un subdominio generado:
  `salmon-glacier-0a1b2c3.azurestaticapps.net`.

**Después de crear los recursos en Azure, anota las URLs reales y actualiza:**

1. `config/cors.php` → array `allowed_origins` → URL real del frontend.
2. `frontend/.env.production` → `VITE_API_URL` → URL real de la API + `/api`.
3. App Service → Environment variables → `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`,
   `SPA_URL` → URLs reales.
4. Haz `git commit` + `git push` de los cambios en (1) y (2) para que se
   redesplieguen backend y frontend.

---

## 4. Pasos en el portal de Azure (en orden)

### 4.1. Resource Group

1. Portal de Azure → **Create a resource** → **Resource group**.
2. Nombre: `novacommerce-rg`. Región: elige una cercana (ej. *West Europe*) y
   **usa la misma para todos los recursos**.
3. **Review + create → Create**.

### 4.2. MySQL Flexible Server

1. **Create a resource** → busca **Azure Database for MySQL Flexible Server** →
   **Create**.
2. **Basics**:
   - Resource group: `novacommerce-rg`
   - Server name: `novacommerce-db` (debe ser único globalmente)
   - Region: la misma del Resource Group
   - MySQL version: **8.0**
   - Workload type: **For development** (o **Burstable**)
   - Compute + storage: **Burstable, Standard_B1ms** (incluido en la capa
     gratuita los primeros 12 meses)
   - Authentication method: **MySQL authentication only**
   - Admin username: `novaadmin` (no uses `admin` ni `root`, están reservados)
   - Password: define una contraseña fuerte y **guárdala**.
3. **Networking**:
   - Connectivity method: **Public access**
   - Marca ✅ **Allow public access from any Azure service within Azure to this server**
   - Marca ✅ **Add current client IP address** (para administrar la BD desde tu PC)
4. **Review + create → Create**. Tarda varios minutos.

### 4.3. Crear la base de datos `novacommerce`

1. Abre el servidor `novacommerce-db` → menú lateral **Databases**.
2. **+ Add** → Name: `novacommerce` → **Save**.

### 4.4. Firewall del MySQL (verificación)

1. Servidor `novacommerce-db` → **Settings → Networking**.
2. Confirma que está marcada ✅ **Allow public access from any Azure service…**.
   Esto permite que el App Service se conecte. Guarda si cambiaste algo.

> Anota el **Server name** completo (algo como
> `novacommerce-db.mysql.database.azure.com`): es tu `DB_HOST`.

### 4.5. App Service (backend Laravel)

1. **Create a resource** → **Web App** → **Create**.
2. **Basics**:
   - Resource group: `novacommerce-rg`
   - Name: `novacommerce-api` (si no está libre, elige otro y actualiza las URLs)
   - Publish: **Code**
   - Runtime stack: **PHP 8.3**
   - Operating System: **Linux**
   - Region: la misma del resto
   - Pricing plan: **Basic B1**
3. **Review + create → Create**.

### 4.6. Conectar el App Service con GitHub (deploy automático)

1. App Service `novacommerce-api` → **Deployment → Deployment Center**.
2. Source: **GitHub** → autoriza tu cuenta.
3. Organization: tu usuario · Repository: el repo de NovaCommerce · Branch: `main`.
4. Build provider: **GitHub Actions**.
5. **Save**. Azure crea un workflow en `.github/workflows/` y lanza el primer
   despliegue del backend.

### 4.7. Añadir las variables de entorno

1. App Service → **Settings → Environment variables → App settings**.
2. Añade todas las variables del **apartado 2** de este documento.
3. Rellena `DB_USERNAME` (`novaadmin`) y `DB_PASSWORD` con los datos del MySQL.
4. **Apply** y confirma el reinicio.

### 4.8. Configurar el Startup Command

1. App Service → **Settings → Configuration** → pestaña **General settings**.
2. **Startup Command**:
   ```
   bash /home/site/wwwroot/startup.sh
   ```
3. **Save**. Esto copia la config de nginx, cachea config/rutas, ejecuta las
   migraciones (`migrate --force`), crea el enlace de `storage` y ajusta permisos.

### 4.9. Static Web App (frontend Vue)

1. **Create a resource** → **Static Web App** → **Create**.
2. **Basics**:
   - Resource group: `novacommerce-rg`
   - Name: `novacommerce`
   - Plan type: **Free**
   - Region: la más cercana disponible
   - Deployment details → Source: **Other**
     *(elegir "Other" en lugar de "GitHub" evita que Azure genere un workflow
     duplicado: ya tienes `azure-static-web-apps.yml` en el repo).*
3. **Review + create → Create**.

> ℹ️ El enrutado de la SPA (rutas tipo `/catalogo`, `/producto/5`) lo resuelve
> `frontend/public/staticwebapp.config.json`, que Vite copia a `dist/` en cada
> build. Sin ese archivo, refrescar una ruta interna devolvería un `404`.

### 4.10. Token de la Static Web App → secret de GitHub

1. Static Web App `novacommerce` → **Overview → Manage deployment token** →
   copia el token.
2. En GitHub: repo → **Settings → Secrets and variables → Actions → New
   repository secret**.
   - Name: `AZURE_STATIC_WEB_APPS_API_TOKEN`
   - Secret: pega el token.
3. Lanza el workflow: haz un commit cualquiera a `main` o ve a la pestaña
   **Actions** del repo y ejecuta *Azure Static Web Apps CI/CD* manualmente.

---

## 5. Verificar que todo funciona

1. **API viva**: abre `https://<tu-app-service>.azurewebsites.net/api/products`
   → debe devolver JSON con productos.
2. **Migraciones**: en la BD `novacommerce` deben existir las tablas (`users`,
   `products`, `sessions`, etc.). Revísalo desde *Azure Cloud Shell* o MySQL
   Workbench.
3. **Frontend**: abre la URL de la Static Web App → la tienda debe cargar y
   mostrar el catálogo (eso ya prueba que el frontend llega a la API).
4. **Auth**: regístrate / inicia sesión → no debe haber errores de CORS en la
   consola del navegador (F12).
5. **CORS**: si la consola muestra `blocked by CORS policy`, repasa el
   **apartado 3** (las URLs reales deben coincidir en `cors.php` y en las
   variables `SPA_URL` / `SANCTUM_STATEFUL_DOMAINS`).

---

## 6. Comandos útiles para depurar

### Log stream en vivo (backend)
App Service → **Monitoring → Log stream**. Muestra errores de nginx/PHP/Laravel
en tiempo real.

### SSH al contenedor del App Service
App Service → **Development Tools → SSH**. Una vez dentro:

```bash
cd /home/site/wwwroot
php artisan about              # estado de la app y entorno
php artisan migrate:status     # estado de las migraciones
php artisan config:clear       # limpiar caché de config si algo va raro
cat storage/logs/laravel.log   # log de la aplicación
tail -f /var/log/nginx/error.log
```

### Probar la conexión a MySQL desde el App Service (SSH)
```bash
php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';"
```

### GitHub Actions
Pestaña **Actions** del repo: revisa si fallan los workflows de backend
(App Service) o de frontend (Static Web App) y lee los logs del paso que falle.

### Errores frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| `500` en toda la API | `APP_KEY` ausente o config sin cachear | Verifica `APP_KEY`; en SSH `php artisan config:clear` |
| `SQLSTATE[HY000] [2002]` / SSL | Fallo de conexión MySQL | Revisa `DB_*`, el firewall (4.4) y `MYSQL_ATTR_SSL_CA` |
| `blocked by CORS policy` | URLs reales ≠ configuradas | Apartado 3 |
| `404` al refrescar rutas del frontend | Falta el fallback de SPA | Confirma que `frontend/public/staticwebapp.config.json` existe y se copió a `dist/` en el build |
| El build de backend no instala dependencias | Falta `SCM_DO_BUILD_DURING_DEPLOYMENT` | Añádela en Environment variables |
| Error `require_secure_transport` | MySQL exige SSL y PDO no lo usa | Confirma `MYSQL_ATTR_SSL_CA`; como último recurso, pon el parámetro de servidor `require_secure_transport` a `OFF` |
