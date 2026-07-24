# VegasRoyalSpin PHP Deep Analysis Report

**Date:** 2026-07-24 23:53:29

## Summary

| Metric | Value |
|--------|-------|
| PHP Files Analyzed | 673 |
| PHP Lines of Code | 104,554 |
| Total Issues | 2424 |

### By Severity

| Severity | Count |
|----------|-------|
| CRITICAL | 54 |
| HIGH | 399 |
| MEDIUM | 1160 |
| LOW | 811 |
| INFO | 0 |

### By Category

| php-security | 1317 |
| php-architecture | 1022 |
| php-quality | 85 |

### Top Checks

| duplicate-function | 807 |
| xss | 681 |
| insecure-function | 567 |
| duplicate-file | 110 |
| duplicate-class | 105 |
| empty-catch | 79 |
| sql-injection | 55 |
| hardcoded-secret | 11 |
| try-without-catch | 3 |
| missing-csrf | 3 |
| debug-code | 2 |
| hard-exit | 1 |

## Top 15 Files

- **admin\services\BgamingService.php** — 104 issues
- **admin\config\env.php** — 66 issues
- **admin\controllers\Api\ApiMemberV2BridgeController.php** — 60 issues
- **admin\app\Views\users\detail.php** — 43 issues
- **admin\app\Views\mobile-menu\edit.php** — 42 issues
- **admin\services\MegaPayzService.php** — 42 issues
- **admin\app\Views\homepage-sections\edit.php** — 40 issues
- **admin\api\SiteSettings.php** — 34 issues
- **admin\services\BackendMemberApiProxy.php** — 34 issues
- **admin\controllers\Api\ApiAuthController.php** — 33 issues
- **mobile\views\layouts\head.php** — 33 issues
- **admin\app\Views\users\_edit_form.php** — 27 issues
- **admin\services\BackendApiClient.php** — 27 issues
- **admin\api\CmsRemote.php** — 26 issues
- **admin\app\Views\bgaming\campaigns.php** — 26 issues

## CRITICAL Issues (54)

### hardcoded-secret (11)

- **`admin\api\Paths.php:13`** — Hardcoded password found
  ```
  PASSWORD   = 'forgot_password.php';
    public const RESET_PASSWORD    = 'reset_
  ```
- **`admin\api\Paths.php:14`** — Hardcoded password found
  ```
  PASSWORD    = 'reset_password.php';
    public const PASSWORD_RESET    = 'passwo
  ```
- **`admin\api\v2\routes\member_auth.php:34`** — Hardcoded token/encryption key
  ```
  token=' . rawurlencode($token);
        return $base !== '' ? ($base . $path) :
  ```
- **`admin\app\Services\AdminInstaller.php:330`** — Hardcoded token/encryption key
  ```
  TOKEN=';
            $lines[] = '';
        }

        $target = $this->root . '
  ```
- **`api\Paths.php:13`** — Hardcoded password found
  ```
  PASSWORD   = 'forgot_password.php';
    public const RESET_PASSWORD    = 'reset_
  ```
- **`api\Paths.php:14`** — Hardcoded password found
  ```
  PASSWORD    = 'reset_password.php';
    public const PASSWORD_RESET    = 'passwo
  ```
- **`mobile\views\partials\profile-panel.php:363`** — Hardcoded token/encryption key
  ```
  token="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8
  ```
- **`pages\profile\two-factor.php:83`** — Hardcoded token/encryption key
  ```
  token="<?php echo htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES,
  ```
- **`views\partials\login.php:323`** — Hardcoded password found
  ```
  password="#loginPassword">
                                                    <
  ```
- **`views\partials\register.php:76`** — Hardcoded password found
  ```
  password="#modal_password" style="position:absolute;right:8px;top:50%;transform:
  ```

  ... and 1 more

### sql-injection (43)

- **`admin\api\SiteSettings.php:401`** — SQL injection risk: variable inside query string
  ```
  ->exec('ALTER TABLE `site_ayarlar` ADD COLUMN `' . str_replace('`', '``', $name) . '` ' . $definitio
  ```
- **`admin\api\v2\index.php:57`** — SQL injection risk: variable inside query string
  ```
  ->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            } catch (Throwable) {
  ```
- **`admin\api\v2\index.php:154`** — SQL injection risk: variable inside query string
  ```
  ->query("SELECT COUNT(*) FROM megapayz_transactions WHERE type='deposit' AND status IN ({$targetStat
  ```
- **`admin\api\v2\index.php:155`** — SQL injection risk: variable inside query string
  ```
  ->query("SELECT COUNT(*) FROM megapayz_transactions WHERE type='withdraw' AND status IN ({$targetSta
  ```
- **`admin\api\v2\index.php:157`** — SQL injection risk: variable inside query string
  ```
  ->query("SELECT COUNT(*) FROM megapayz_transactions WHERE status NOT IN ({$targetStatuses})")->fetch
  ```
- **`admin\api\v2\index.php:185`** — SQL injection risk: variable inside query string
  ```
  ->exec("DELETE FROM megapayz_transactions WHERE status IN ({$targetStatuses})");
            $delete
  ```
- **`admin\api\v2\index.php:194`** — SQL injection risk: variable inside query string
  ```
  ->exec("ALTER TABLE megapayz_transactions AUTO_INCREMENT = " . ($maxId + 1));
            $pdo->exec
  ```
- **`admin\api\v2\routes\member_auth.php:261`** — SQL injection risk: variable inside query string
  ```
  ->exec("ALTER TABLE `users` ADD COLUMN `{$col}` {$def}");
                error_log('[memberEnsureUs
  ```
- **`admin\api\v2\routes\member_auth.php:274`** — SQL injection risk: variable inside query string
  ```
  ->query("SHOW INDEX FROM `users` WHERE Key_name = " . $pdo->quote($idx))->fetchColumn();
  ```
- **`admin\app\Controllers\AdminCommunicationController.php:397`** — SQL injection risk: variable inside query string
  ```
  ->query(
                'SELECT * FROM `' . str_replace('`', '``', $table) . '` ORDER BY `' . str_r
  ```

  ... and 33 more


## HIGH Issues (399)

### duplicate-file (110)

- **`admin\api\AccountFreeze.php:0`** — Identical file in 2 locations
  ```
  AccountFreeze.php
  ```
- **`admin\api\Announcements.php:0`** — Identical file in 2 locations
  ```
  Announcements.php
  ```
- **`admin\api\AuthSliders.php:0`** — Identical file in 2 locations
  ```
  AuthSliders.php
  ```
- **`admin\api\Bases.php:0`** — Identical file in 2 locations
  ```
  Bases.php
  ```
- **`admin\api\bootstrap.php:0`** — Identical file in 2 locations
  ```
  bootstrap.php
  ```
- **`admin\api\CallMeRequest.php:0`** — Identical file in 2 locations
  ```
  CallMeRequest.php
  ```
- **`admin\api\Client.php:0`** — Identical file in 2 locations
  ```
  Client.php
  ```
- **`admin\api\DepositHistory.php:0`** — Identical file in 2 locations
  ```
  DepositHistory.php
  ```
- **`admin\api\Envelope.php:0`** — Identical file in 2 locations
  ```
  Envelope.php
  ```
- **`admin\api\FooterPages.php:0`** — Identical file in 2 locations
  ```
  FooterPages.php
  ```

  ... and 100 more

### insecure-function (274)

- **`admin\api\AuthSliders.php:10`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS auth_sliders (
                id INT UNSIGNED NOT NUL
  ```
- **`admin\api\Footer.php:144`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS footer_settings (
                id INT UNSIGNED NOT
  ```
- **`admin\api\FooterPages.php:84`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS footer_pages (
                id INT UNSIGNED NOT NUL
  ```
- **`admin\api\HomepageSections.php:111`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS homepage_sections (
                id INT UNSIGNED NO
  ```
- **`admin\api\Loyalty.php:134`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS loyalty_levels (
                id INT UNSIGNED NOT N
  ```
- **`admin\api\Loyalty.php:154`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS user_loyalty_accounts (
                id BIGINT UNSI
  ```
- **`admin\api\Loyalty.php:172`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS loyalty_point_transactions (
                id BIGINT
  ```
- **`admin\api\MobileMenu.php:185`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS mobile_menu_settings (
                id INT UNSIGNED
  ```
- **`admin\api\SiteSettings.php:386`** — exec() - shell command execution
  ```
  exec(
            "CREATE TABLE IF NOT EXISTS `site_ayarlar` (
                `id` int unsigned NOT
  ```
- **`admin\api\SiteSettings.php:401`** — exec() - shell command execution
  ```
  exec('ALTER TABLE `site_ayarlar` ADD COLUMN `' . str_replace('`', '``', $name) . '` ' . $definition)
  ```

  ... and 264 more

### missing-csrf (3)

- **`pages\profile\sadakat-puanlari.php:1`** — POST form without visible CSRF protection
- **`views\partials\login.php:1`** — POST form without visible CSRF protection
- **`views\partials\reset-password-section.php:1`** — POST form without visible CSRF protection

### sql-injection (12)

- **`admin\api\v2\includes\admin_routes.php:24`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchColumn();
            } catch (Throwable) {
                return 0.0;
  ```
- **`admin\api\v2\includes\admin_routes.php:46`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchColumn();
            } catch (Throwable) {
                return 0.0;
  ```
- **`admin\api\v2\routes\member_games.php:44`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql);
            $providers = $pStmt ? $pStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }
  ```
- **`admin\app\Controllers\AdminBackofficeSuiteController.php:178`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
  ```
- **`admin\app\Controllers\AdminDashboardController.php:403`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchColumn();

            return (float) $value;
        } catch (Throwable $e) {
  ```
- **`admin\app\Controllers\AdminReportController.php:124`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql);
                foreach ($stmt->fetchAll() as $row) {
                    $events[] =
  ```
- **`admin\app\Controllers\AdminReportController.php:138`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0.0;
        }
    }
  ```
- **`admin\app\Views\layouts\app.php:100`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
};
$layoutRows = sta
  ```
- **`admin\app\Views\layouts\app.php:107`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
};
$adminName = (strin
  ```
- **`admin\app\Views\tables\show.php:176`** — Dynamic SQL query - verify prepared statement usage
  ```
  ->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0.0;
    }
};
$money = static
  ```

  ... and 2 more


## MEDIUM Issues (1160)

### debug-code (2)

- **`debug_favicon.php:9`** — var_dump() debug function in production code
  ```
  var_dump($siteBranding ?? []);

echo "\n2. \$headBranding wo
  ```
- **`debug_favicon.php:13`** — var_dump() debug function in production code
  ```
  var_dump($headBranding);

echo "\n3. Favicon URL calculation
  ```

### duplicate-class (105)

- **`admin\api\AccountFreeze.php:0`** — Class "ApiAccountFreeze" defined in 2 files
  ```
  AccountFreeze.php
  ```
- **`admin\api\AuthSliders.php:0`** — Class "ApiAuthSliders" defined in 2 files
  ```
  AuthSliders.php
  ```
- **`admin\api\Bases.php:0`** — Class "ApiBases" defined in 2 files
  ```
  Bases.php
  ```
- **`admin\api\CallMeRequest.php:0`** — Class "ApiCallMeRequest" defined in 2 files
  ```
  CallMeRequest.php
  ```
- **`admin\api\Client.php:0`** — Class "ApiClient" defined in 2 files
  ```
  Client.php
  ```
- **`admin\api\CmsRemote.php:0`** — Class "ApiCmsRemote" defined in 2 files
  ```
  CmsRemote.php
  ```
- **`admin\api\DepositHistory.php:0`** — Class "ApiDepositHistory" defined in 2 files
  ```
  DepositHistory.php
  ```
- **`admin\api\Envelope.php:0`** — Class "ApiEnvelope" defined in 2 files
  ```
  Envelope.php
  ```
- **`admin\api\Footer.php:0`** — Class "ApiFooter" defined in 2 files
  ```
  Footer.php
  ```
- **`admin\api\FooterPages.php:0`** — Class "ApiFooterPages" defined in 2 files
  ```
  FooterPages.php
  ```

  ... and 95 more

### empty-catch (79)

- **`admin\api\CmsRemote.php:214`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\Sliders.php:109`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\Sliders.php:113`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\v2\includes\admin_routes.php:442`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\v2\includes\admin_routes.php:746`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\v2\includes\admin_routes.php:769`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\v2\includes\admin_routes.php:786`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\v2\includes\admin_routes.php:1169`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\v2\includes\admin_routes.php:1400`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```
- **`admin\api\v2\routes\member_auth.php:99`** — Empty catch block - silently ignoring errors
  ```
  catch (...) { }
  ```

  ... and 69 more

### insecure-function (293)

- **`admin\api\Bases.php:88`** — Dynamic file inclusion - verify path sanitization
  ```
  require_once $path;
        }
    }
}
  ```
- **`admin\api\Client.php:16`** — Dynamic file inclusion - verify path sanitization
  ```
  require_once $path;
        }
    }

    /**
     * Split frontend: public HTTPS base yerine interna
  ```
- **`admin\api\CmsRemote.php:471`** — Dynamic file inclusion - verify path sanitization
  ```
  require_once $client;
        }
    }
}
  ```
- **`admin\api\CmsRemote.php:314`** — Error suppression (@) hides failures
  ```
  @mkdir($dir, 0755, true);
        }
        @touch($path);

        return true;
    }

    /**
  ```
- **`admin\api\CmsRemote.php:346`** — Error suppression (@) hides failures
  ```
  @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return n
  ```
- **`admin\api\CmsRemote.php:382`** — Error suppression (@) hides failures
  ```
  @file_put_contents($path, $payload, LOCK_EX);
        }
    }

    private static function canWriteC
  ```
- **`admin\api\CmsRemote.php:396`** — Error suppression (@) hides failures
  ```
  @mkdir($dir, 0755, true);

        return $created && is_writable($dir);
    }

    private static f
  ```
- **`admin\api\CmsRemote.php:453`** — Error suppression (@) hides failures
  ```
  @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
  ```
- **`admin\api\Jackpot.php:51`** — Dynamic file inclusion - verify path sanitization
  ```
  require $configPath;
        }

        return [
            'epoch' => (string) ($jackpotEpoch ?? d
  ```
- **`admin\api\MediaUrl.php:21`** — Dynamic file inclusion - verify path sanitization
  ```
  require_once $path;
        }
    }

    public static function resolve(string $path): string
    {
  ```

  ... and 283 more

### xss (681)

- **`admin\api\v2\includes\profile_detail_html.php:578`** — Short echo without escaping - potential XSS
  ```
  <?= $netTone === 'success' ? 'text-success' : 'text-danger' ?>"><?= ($netAmount >= 0 ? '+' : '') . n
  ```
- **`admin\api\v2\includes\profile_detail_html.php:581`** — Short echo without escaping - potential XSS
  ```
  <?= $balanceAfter !== null && $balanceAfter !== '' ? number_format((float) $balanceAfter, 2) . ' ₺'
  ```
- **`admin\app\Views\bgaming\campaigns.php:52`** — Short echo without escaping - potential XSS
  ```
  <?= $text(AdminAuth::url('/bgaming/settings')) ?>">BGaming Ayarları</a>
        <a class="btn btn--s
  ```
- **`admin\app\Views\bgaming\campaigns.php:53`** — Short echo without escaping - potential XSS
  ```
  <?= $text(AdminAuth::url('/bgaming/campaigns/assignments')) ?>">Kampanya Ata</a>
    </div>
</sectio
  ```
- **`admin\app\Views\bgaming\campaigns.php:58`** — Short echo without escaping - potential XSS
  ```
  <?= $text($flash) ?></div>
<?php endif; ?>

<div class="bgaming-campaign-grid">
    <div class="bgam
  ```
- **`admin\app\Views\bgaming\campaigns.php:65`** — Short echo without escaping - potential XSS
  ```
  <?= $isEdit ? 'Kampanya Düzenle' : 'Yeni Kampanya' ?></h2>
            </div>
            <div class
  ```
- **`admin\app\Views\bgaming\campaigns.php:68`** — Short echo without escaping - potential XSS
  ```
  <?= $text(AdminAuth::url('/bgaming/campaigns/store')) ?>">
                    <input type="hidden"
  ```
- **`admin\app\Views\bgaming\campaigns.php:69`** — Short echo without escaping - potential XSS
  ```
  <?= $text(AdminAuth::csrfToken()) ?>">
                    <?php if ($isEdit): ?><input type="hidden
  ```
- **`admin\app\Views\bgaming\campaigns.php:70`** — Short echo without escaping - potential XSS
  ```
  <?= $campaign('id') ?>"><?php endif; ?>
                    <div class="bgaming-inline-form">
  ```
- **`admin\app\Views\bgaming\campaigns.php:74`** — Short echo without escaping - potential XSS
  ```
  <?= $campaign('title') ?>">
                        </div>
                        <div class="field
  ```

  ... and 671 more


## LOW Issues (811)

### duplicate-function (807)

- **`admin\api\AccountFreeze.php:0`** — Function "submitEnvelope" defined in 6 files
  ```
  AccountFreeze.php, PasswordUpdate.php, ProfileUpdate.php
  ```
- **`admin\api\AuthSliders.php:0`** — Function "ensureStorage" defined in 18 files
  ```
  AdminPermissionController.php, AdminSiteSettingsController.php, AuthSliders.php, Footer.php, FooterPages.php, HomepageSe
  ```
- **`admin\api\AuthSliders.php:0`** — Function "fetchFor" defined in 2 files
  ```
  AuthSliders.php
  ```
- **`admin\api\AuthSliders.php:0`** — Function "mapRows" defined in 6 files
  ```
  AuthSliders.php, HomepageSections.php, Sliders.php
  ```
- **`admin\api\AuthSliders.php:0`** — Function "mapRemoteRows" defined in 2 files
  ```
  AuthSliders.php
  ```
- **`admin\api\AuthSliders.php:0`** — Function "pdo" defined in 22 files
  ```
  AdminDatabase.php, AuthSliders.php, CmsRemote.php, Database.php, Footer.php, FooterPages.php, HomepageSections.php, Loya
  ```
- **`admin\api\Bases.php:0`** — Function "forMemberApi" defined in 2 files
  ```
  Bases.php
  ```
- **`admin\api\Bases.php:0`** — Function "forMemberApiWithGames" defined in 2 files
  ```
  Bases.php
  ```
- **`admin\api\Bases.php:0`** — Function "ensureBackendClient" defined in 6 files
  ```
  Bases.php, Client.php, CmsRemote.php
  ```
- **`admin\api\CallMeRequest.php:0`** — Function "fetchEnvelope" defined in 20 files
  ```
  CallMeRequest.php, DepositHistory.php, GameHistory.php, GamesProvider.php, MemberAnnouncements.php, MemberInboxMessages.
  ```

  ... and 797 more

### hard-exit (1)

- **`check_db.php:5`** — Hard exit/die with message - poor error UX
  ```
  die("env dosyası bulunamadı\n");
}

$env_content = file_get_
  ```

### try-without-catch (3)

- **`admin\app\Services\SqlSeedImporter.php:1`** — 5 try blocks vs 3 catch blocks - possible missing catch
- **`admin\controllers\Api\ApiMemberV2BridgeController.php:1`** — 1 try blocks vs 0 catch blocks - possible missing catch
- **`controllers\Api\ApiMemberV2BridgeController.php:1`** — 1 try blocks vs 0 catch blocks - possible missing catch

