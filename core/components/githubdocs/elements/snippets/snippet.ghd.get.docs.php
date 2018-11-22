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

/** @var GitHubDocs|null $ghd */
$ghd = $modx->getService('githubdocs', 'GitHubDocs', $modx->getOption('githubdocs.core_path', null, MODX_CORE_PATH . 'components/githubdocs/') . 'model/githubdocs/', $scriptProperties);
if (!($ghd instanceof GitHubDocs)) return $modx->log(MODX::LOG_LEVEL_ERROR, 'Service class not loaded');

//settings
$repo_url = $modx->getOption('repoUrl', $scriptProperties, '');
$docs_path = $modx->getOption('docsPath', $scriptProperties, '');
$private = ($modx->getOption('private', $scriptProperties) === '1') ? true : false;
$parse = ($modx->getOption('parse', $scriptProperties) !== '0') ? true : false;
$debug = ($modx->getOption('debug', $scriptProperties) === '1') ? true : false;
//cache
$cache_expires = $modx->getOption('cacheExpires', $scriptProperties, 86400); //TODO move to sys settings
$cache_opts = array(
    xPDO::OPT_CACHE_KEY => ($cache_key = $modx->getOption('cacheKey', $scriptProperties)) ? 'gitHubDocs/' . $cache_key : 'gitHubDocs' //TODO move to sys settings
);

$output = '';

try {
    if ($repo_url && $docs_path) {
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
        if (!$response = $modx->cacheManager->get(md5($repo_url . $uri), $cache_opts)) {
            $response = $ghd->get($repo_url, $uri, $private, $parse);
            $modx->cacheManager->set(md5($repo_url . $uri), $response, $cache_expires, $cache_opts);
        }
        //output
        if ($debug) {
            $output = '<pre>' . print_r($response, true) . '</pre>';
        } else {
            $modx->setPlaceholders($response, 'ghd.');
        }
    } else {
        throw new Exception('Snippet [[ghdGetDocs]] requires &repoUrl and &docsPath parameters.');
    }
} catch (\GuzzleHttp\Exception\GuzzleException | Exception $e) {
    $ghd->exceptionHandler($e);
    return;
}

return $output;
