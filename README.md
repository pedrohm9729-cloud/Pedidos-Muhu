# Pedidos-Muhu

Aplicación web de gestión de pedidos de insumos para la cafetería Muhu en Perú.
- URL de producción: `pedidos.muhucafeteria.com`
- Stack: PHP 8.3 + Vanilla JS
- Destino de ingesta: `ops.muhucafeteria.com`

## Roles y vistas

Cada usuario en `$PEDIDOS_USERS` (config.php) tiene un `role`:

- **`admin`** → panel de **gestión de pedidos en curso**: ve todos los pedidos
  del personal con su estado (nuevo / en preparación / completado / anulado) y
  totales, y puede cambiar el estado de cada uno.
- **`staff`** (por defecto) → pantalla para **crear un nuevo pedido** (catálogo
  de insumos + carrito). Ej.: `sandra`, `trabajador`.

Si un usuario no define `role`, se infiere: `admin` → administrador; cualquier
otro → personal.

## Persistencia local

El servidor central sólo expone ingesta (no listado), por lo que esta app guarda
una copia local de cada pedido en `data/pedidos.json` para alimentar el panel del
admin. El directorio `data/` está protegido del acceso web (`.htaccess`), no se
versiona (`.gitignore`) y se excluye del `rsync --delete` del deploy para que no
se borre en cada despliegue.

## Deploy (GitHub Actions → Hostinger vía SSH/rsync)

`config.php` se genera en el pipeline desde *secrets*. Para que los logins
funcionen deben existir estos secrets en el repositorio:

| Secret | Uso |
| --- | --- |
| `OPS_INGEST_TOKEN` | Token de ingesta hacia `ops.muhucafeteria.com` |
| `ADMIN_PASSWORD_HASH` | Hash bcrypt del usuario `admin` (rol admin) |
| `SANDRA_PASSWORD_HASH` | Hash bcrypt de `sandra` (rol staff) |
| `PEDIDOS_USER_HASH` | Hash bcrypt de `trabajador` (rol staff) |
| `SSH_HOST` / `SSH_USER` / `DEPLOY_PATH` / `SSH_PRIVATE_KEY` | Acceso al hosting |

Genera un hash con: `php -r 'echo password_hash("LA_CLAVE", PASSWORD_BCRYPT, ["cost"=>12]);'`
