<?php

/**
 * Article new view
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Security/HtmlSanitizer.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";

// Load app modules
$App = new App(true);
$App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

try {

    // Check if user is logged
    $User = new User();
    if (!$User->_isLogged()) {
        throw new Exception("User not logged", 1);
    }

    // Init language object
    $Lang = new Lang($User->_getLang(), $App);

    // Init news object
    $New = new News();

    // Article selected
    $ArticleSelected = $New->_getArticle($_POST['uniqnews']);

} catch (Exception $e) {
    die(json_encode([
      'error' => 1,
      'msg' => $e->getMessage()
    ]));
}

?>
<header style="<?php echo(empty($ArticleSelected->_getPicture()) ? 'display:none;' : ''); ?> background-image:url('<?php echo htmlspecialchars(HtmlSanitizer::safeUrl($ArticleSelected->_getPicture()), ENT_QUOTES, 'UTF-8'); ?>')">

</header>
<section class="kr-news-detailed-infos">
  <div>
    <div class="kr-news-detailed-infos-data">
      <label><?php echo htmlspecialchars($ArticleSelected->_getFrom(), ENT_QUOTES, 'UTF-8'); ?></label>
      <span><?php echo htmlspecialchars($ArticleSelected->_getAuthor(), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
  </div>
  <ul>
    <?php
    foreach ($ArticleSelected->_getListTags() as $keyTag => $valTag) {
        echo '<li class="kr-news-tags-'.($keyTag % 5).'">'.htmlspecialchars($valTag, ENT_QUOTES, 'UTF-8').'</li>';
    }
    ?>
  </ul>
</section>
<h1><?php echo htmlspecialchars($ArticleSelected->_getTitle(), ENT_QUOTES, 'UTF-8'); ?></h1>
<div class="kr-news-content"><?php echo $ArticleSelected->_getContent(); ?></div>
<footer>
  <a href="<?php echo htmlspecialchars(HtmlSanitizer::safeUrl($ArticleSelected->_getUrl()), ENT_QUOTES, 'UTF-8'); ?>" target=_bank rel="noopener noreferrer nofollow" class="btn btn-orange btn-autowidth"><?php echo $Lang->tr('View the article'); ?></a>
</footer>
