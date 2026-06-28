# Apache yapılandırması

Bu proje **nginx gerektirmez**. Production split deploy:

| Dosya | Kullanım |
|-------|----------|
| `vegasroyalspin.com.htaccess` | Frontend site kökü → zip otomatik `.htaccess` yapar |
| `bo-nexthub.site.htaccess` | Backend site kökü |
| `vegasroyalspin.com.conf` | aaPanel / Apache vhost örneği (referans) |
| `bo-nexthub.site.conf` | aaPanel / Apache vhost örneği (referans) |

aaPanel'de site oluştururken **Apache** seçin, nginx reverse proxy kapalı olsun.
