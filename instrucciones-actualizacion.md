# Configurar Actualizaciones Automáticas para tus Plugins Privados

## Opción 1: GitHub (Gratis y Recomendado)

### Pasos:

1. **Crear repositorio en GitHub**
   - Crea un repo privado: `github.com/tu-usuario/seo-image-optimizer`
   - Sube el plugin completo

2. **Modificar el plugin principal**
   ```php
   // Añadir al final de seo-image-optimizer.php
   require_once 'updater.php';
   new SEO_Image_Optimizer_Updater(__FILE__);
   ```

3. **Configurar el updater.php**
   - Cambia `TU_USUARIO_GITHUB` por tu usuario real
   - Ajusta el nombre del repositorio si es diferente

4. **Crear releases en GitHub**
   - Ve a "Releases" → "Create new release"
   - Tag version: `1.1` (debe ser mayor que la actual)
   - Sube el .zip del plugin o usa el código fuente

5. **WordPress detectará automáticamente las actualizaciones**

## Opción 2: Servidor Propio

### Estructura necesaria:

```
tu-servidor.com/
├── plugins/
│   ├── seo-image-optimizer.zip
│   └── info.json
```

### info.json:
```json
{
  "name": "SEO Image Optimizer",
  "slug": "seo-image-optimizer",
  "version": "1.1",
  "download_url": "https://tu-servidor.com/plugins/seo-image-optimizer.zip",
  "sections": {
    "description": "Optimiza nombres de imágenes para SEO",
    "changelog": "<h4>1.1</h4><ul><li>Nueva funcionalidad X</li></ul>"
  }
}
```

## Opción 3: WP Pusher (Integración Git)

1. Instala WP Pusher en tu WordPress
2. Conecta tu cuenta GitHub/Bitbucket/GitLab
3. Push to Deploy activado

## Opción 4: ManageWP o MainWP

Para gestionar múltiples sitios:
- MainWP: $29/mes para sitios ilimitados
- ManageWP: Gratis hasta 5 sitios

## Script de Despliegue Rápido

```bash
#!/bin/bash
# deploy.sh - Para actualizar todos tus WordPress

SITES=(
  "sitio1.com:/wp-content/plugins/"
  "sitio2.com:/wp-content/plugins/"
)

for site in "${SITES[@]}"; do
  rsync -avz ./seo-image-optimizer/ user@$site
done
```

## Recomendación Final

**Para plugins privados simples:** GitHub + updater.php incluido
**Para agencias con muchos sitios:** MainWP o ManageWP
**Para equipos de desarrollo:** WP Pusher con CI/CD

---

### Token de GitHub (si el repo es privado)

Si usas un repositorio privado, necesitas un token de acceso:

1. GitHub → Settings → Developer settings → Personal access tokens
2. Generate new token con permisos `repo`
3. Añadir al updater:

```php
$response = wp_remote_get($request_uri, array(
    'headers' => array(
        'Accept' => 'application/vnd.github.v3+json',
        'Authorization' => 'token TU_TOKEN_AQUI'
    ),
));
```