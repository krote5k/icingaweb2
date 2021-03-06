<?php
/* Icinga Web 2 | (c) 2014 Icinga Development Team | GPLv2+ */

namespace Icinga\Web;

use Icinga\Application\Icinga;
use Icinga\Web\FileCache;
use JShrink\Minifier;

class JavaScript
{
    protected static $jsFiles = array(
        'js/helpers.js',
        'js/icinga.js',
        'js/icinga/logger.js',
        'js/icinga/storage.js',
        'js/icinga/utils.js',
        'js/icinga/ui.js',
        'js/icinga/timer.js',
        'js/icinga/loader.js',
        'js/icinga/eventlistener.js',
        'js/icinga/events.js',
        'js/icinga/history.js',
        'js/icinga/module.js',
        'js/icinga/timezone.js',
        'js/icinga/behavior/application-state.js',
        'js/icinga/behavior/autofocus.js',
        'js/icinga/behavior/collapsible.js',
        'js/icinga/behavior/detach.js',
        'js/icinga/behavior/sparkline.js',
        'js/icinga/behavior/dropdown.js',
        'js/icinga/behavior/navigation.js',
        'js/icinga/behavior/form.js',
        'js/icinga/behavior/actiontable.js',
        'js/icinga/behavior/flyover.js',
        'js/icinga/behavior/expandable.js',
        'js/icinga/behavior/filtereditor.js',
        'js/icinga/behavior/selectable.js',
        'js/icinga/behavior/modal.js'
    );

    protected static $vendorFiles = array(
        'js/vendor/jquery-3.4.1',
        'js/vendor/jquery-migrate-3.1.0',
        'js/vendor/jquery.sparkline'
    );

    public static function sendMinified()
    {
        self::send(true);
    }

    /**
     * Send the client side script code to the client
     *
     * Does not cache the client side script code if the HTTP header Cache-Control or Pragma is set to no-cache.
     *
     * @param   bool    $minified   Whether to compress the client side script code
     */
    public static function send($minified = false)
    {
        header('Content-Type: application/javascript');
        $basedir = Icinga::app()->getBootstrapDirectory();

        $js = $out = '';
        $min = $minified ? '.min' : '';

        // Prepare vendor file list
        $vendorFiles = array();
        foreach (self::$vendorFiles as $file) {
            $vendorFiles[] = $basedir . '/' . $file . $min . '.js';
        }

        // Prepare Icinga JS file list
        $jsFiles = array();
        foreach (self::$jsFiles as $file) {
            $jsFiles[] = $basedir . '/' . $file;
        }

        $sharedFiles = [];
        foreach (Icinga::app()->getModuleManager()->getLoadedModules() as $name => $module) {
            if ($module->hasJs()) {
                foreach ($module->getJsFiles() as $path) {
                    if (file_exists($path)) {
                        $jsFiles[] = $path;
                    }
                }
            }

            if ($module->requiresJs()) {
                foreach ($module->getJsRequires() as $path) {
                    $sharedFiles[] = $path;
                }
            }
        }

        $sharedFiles = array_unique($sharedFiles);
        $files = array_merge($vendorFiles, $jsFiles, $sharedFiles);

        $request = Icinga::app()->getRequest();
        $noCache = $request->getHeader('Cache-Control') === 'no-cache' || $request->getHeader('Pragma') === 'no-cache';

        header('Cache-Control: public');
        if (! $noCache && FileCache::etagMatchesFiles($files)) {
            header("HTTP/1.1 304 Not Modified");
            return;
        } else {
            $etag = FileCache::etagForFiles($files);
        }
        header('ETag: "' . $etag . '"');
        header('Content-Type: application/javascript');

        $cacheFile = 'icinga-' . $etag . $min . '.js';
        $cache = FileCache::instance();
        if (! $noCache && $cache->has($cacheFile)) {
            $cache->send($cacheFile);
            return;
        }

        // We do not minify vendor files
        foreach ($vendorFiles as $file) {
            $out .= ';' . ltrim(trim(file_get_contents($file)), ';') . "\n";
        }

        foreach ($jsFiles as $file) {
            $js .= file_get_contents($file) . "\n\n\n";
        }

        foreach ($sharedFiles as $file) {
            if (substr($file, -7, 7) === '.min.js') {
                $out .= ';' . ltrim(trim(file_get_contents($file)), ';') . "\n";
            } else {
                $js .= file_get_contents($file) . "\n\n\n";
            }
        }

        if ($minified) {
            require_once 'JShrink/Minifier.php';
            $out .= Minifier::minify($js, array('flaggedComments' => false));
        } else {
            $out .= $js;
        }
        $cache->store($cacheFile, $out);
        echo $out;
    }
}
