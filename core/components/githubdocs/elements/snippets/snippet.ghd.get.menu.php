<?php
/**
 * Generates a navigation menu from a GitHub directory structure.
 *
 * @package gitHubDocs
 * @subpackage snippets
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

/** @var GitHubDocs|null $ghd */
$ghd = $modx->getService('githubdocs', 'GitHubDocs', $modx->getOption('githubdocs.core_path', null, MODX_CORE_PATH . 'components/githubdocs/') . 'model/githubdocs/', $scriptProperties);
if (!($ghd instanceof GitHubDocs)) return $modx->log(MODX::LOG_LEVEL_ERROR, 'Service class not loaded');

//settings
$repo_url = $modx->getOption('repoUrl', $scriptProperties, '');
$docs_path = $modx->getOption('docsPath', $scriptProperties, '');
$private = ($modx->getOption('private', $scriptProperties) === '1') ? true : false;
$debug = ($modx->getOption('debug', $scriptProperties) === '1') ? true : false;
//templates
$file_tpl = $modx->getOption('fileTpl', $scriptProperties, '');
$dir_tpl = $modx->getOption('dirTpl', $scriptProperties, '');
//cache
$cache_expires = $modx->getOption('cacheExpires', $scriptProperties, 86400); //TODO move to sys settings
$cache_opts = array(
    xPDO::OPT_CACHE_KEY => ($cache_key = $modx->getOption('cacheKey', $scriptProperties)) ? 'gitHubDocs/' . $cache_key : 'gitHubDocs' //TODO move to sys settings
);

$output = '';

try {
    if ($repo_url && $docs_path) {
        //request
        if (!$tree = $modx->cacheManager->get(md5($repo_url) . '_tree', $cache_opts)) {
            $tree = $ghd->getDirTree($repo_url, $docs_path, $private);
            $modx->cacheManager->set(md5($repo_url) . '_tree', $tree, $cache_expires, $cache_opts);
        }
        //menu templating
        if ($dir_tpl && $file_tpl) {
            //links
            $base_url = $modx->makeUrl($modx->resource->get('id'));
            $menu = $ghd->styleDirTree($tree, $base_url, $dir_tpl, $file_tpl, $debug);
            //output
            if ($debug) {
                $output = '<pre>' . print_r($menu, true) . '</pre>';
            } else {
                $output = implode("\n", $menu);
            }
        } else {
            throw new Exception('Snippet [[ghdGetMenu]] requires &dirTpl and &fileTpl parameters.');
        }
    } else {
        throw new Exception('Snippet [[ghdGetMenu]] requires &repoUrl and &docsPath parameters.');
    }
} catch (\GuzzleHttp\Exception\GuzzleException | Exception $e) {
    $ghd->exceptionHandler($e);
    return;
}

return $output;
