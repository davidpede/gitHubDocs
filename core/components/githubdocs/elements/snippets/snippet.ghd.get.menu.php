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

//settings
$repo_owner = $modx->getOption('repoOwner', $scriptProperties, '');
$repo_name = $modx->getOption('repoName', $scriptProperties, '');
$docs_path = $modx->getOption('docsPath', $scriptProperties, '');
$private = ($modx->getOption('private', $scriptProperties) === '1') ? true : false;
$debug = ($modx->getOption('debug', $scriptProperties) === '1') ? true : false;
//templating
$file_tpl = $modx->getOption('fileTpl', $scriptProperties, '');
$dir_tpl = $modx->getOption('dirTpl', $scriptProperties, '');
$scriptProperties = array_merge(array(
    'base_url' => $modx->makeUrl($modx->resource->get('id')) . rtrim($docs_path,'/') . '/',
), $scriptProperties);
//cache
$cache_expires = $modx->getOption('cacheExpires', $scriptProperties, 86400); //TODO move to sys settings
$cache_opts = array(
    xPDO::OPT_CACHE_KEY => ($cache_key = $modx->getOption('cacheKey', $scriptProperties)) ? 'gitHubDocs/' . $cache_key : 'gitHubDocs'
);

/** @var GitHubDocs|null $ghd */
$ghd = $modx->getService('githubdocs', 'GitHubDocs', $modx->getOption('githubdocs.core_path', null, MODX_CORE_PATH . 'components/githubdocs/') . 'model/githubdocs/', $scriptProperties);
if (!($ghd instanceof GitHubDocs)) return $modx->log(MODX::LOG_LEVEL_ERROR, 'Service class not loaded');

$output = '';

try {
    if ($repo_owner && $repo_name && $docs_path) {
        //request
        if (!$tree = $modx->cacheManager->get(md5($repo_name . $docs_path) . '_tree', $cache_opts)) {
            $dir = $ghd->getDirectory($repo_owner, $repo_name,'docs', $private);
            $tree = $ghd->getTree($repo_owner, $repo_name, $dir['sha'],$private);
            $modx->cacheManager->set(md5($repo_name . $docs_path) . '_tree', $tree, $cache_expires, $cache_opts);
        }
        //menu templating
        if ($dir_tpl && $file_tpl) {
            //links
            $menu = $ghd->templateTree($tree, $dir_tpl, $file_tpl, $debug, $_REQUEST);
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
        throw new Exception('Snippet [[ghdGetMenu]] requires &repoOwner, &repoName and &docsPath parameters.');
    }
} catch (\GuzzleHttp\Exception\GuzzleException | Exception $e) {
    $ghd->exceptionHandler($e);
    return;
}

return $output;