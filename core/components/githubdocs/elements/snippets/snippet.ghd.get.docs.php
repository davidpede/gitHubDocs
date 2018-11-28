<?php
/**
 * Generates html from md files stored in GitHub Repo.
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
$parse = ($modx->getOption('parse', $scriptProperties) !== '0') ? true : false;
$debug = ($modx->getOption('debug', $scriptProperties) === '1') ? true : false;
//templating
$scriptProperties = array_merge(array(
    'base_url' => $modx->makeUrl($modx->resource->get('id')) . rtrim($docs_path,'/') . '/',
), $scriptProperties);
//cache
$cache_expires = $modx->getOption('cacheExpires', $scriptProperties, 86400); //TODO move to sys settings
$cache_opts = array(
    xPDO::OPT_CACHE_KEY => ($cache_key = $modx->getOption('cacheKey', $scriptProperties)) ? 'gitHubDocs/' . $cache_key : 'gitHubDocs' //TODO move to sys settings
);

/** @var GitHubDocs|null $ghd */
$ghd = $modx->getService('githubdocs', 'GitHubDocs', $modx->getOption('githubdocs.core_path', null, MODX_CORE_PATH . 'components/githubdocs/') . 'model/githubdocs/', $scriptProperties);
if (!($ghd instanceof GitHubDocs)) return $modx->log(MODX::LOG_LEVEL_ERROR, 'Service class not loaded');

$output = '';

try {
    if ($repo_owner && $repo_name) {
        //build request uri from CustomRequest parameters
        $url_parts = array_intersect_key($_REQUEST, array_flip(preg_grep('/^[p][0-9]{1,2}$/', array_keys($_REQUEST))));
        if (!empty($url_parts)) {
            $request_parts = pathinfo($_REQUEST['q']);
            $path = implode('/', $url_parts);
            $uri = isset($request_parts['extension']) ? $path . '.md' : $path; //TODO add logic for dir index file
        } else {
            $uri = $docs_path;
        }
        //request
        if (!$response = $modx->cacheManager->get(md5($repo_name . $uri), $cache_opts)) {
            $response = $ghd->getContents($repo_owner, $repo_name, $uri, $private, $parse);
            $modx->cacheManager->set(md5($repo_name . $uri), $response, $cache_expires, $cache_opts);
        }
        //output
        if ($debug) {
            $output = '<pre>' . print_r($response, true) . '</pre>';
        } else {
            $modx->setPlaceholders($response, 'ghd.');
        }
    } else {
        throw new Exception('Snippet [[ghdGetDocs]] requires &repoOwner and &repoName parameters.');
    }
} catch (\GuzzleHttp\Exception\GuzzleException | Exception $e) {
    $ghd->exceptionHandler($e);
    return;
}

return $output;