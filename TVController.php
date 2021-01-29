<?php
require_once 'controleurs/initControler.php';
require_once 'controleurs/accountsControler.php';
require_once 'controleurs/panelAdditionalInitControler.php';


//The controller for the TvShow in WatchOver , Display the content of a particular tvshow 


if(empty($_GET['id']))
{
    header('location: https://' . $_SERVER["HTTP_HOST"] . '/panel/popular?type=tv', true, 301);
    exit;
}
$currentPage = htmlspecialchars($_GET['page']);
if(empty($currentPage))
{
    $currentPage='overview';
}

$id =  (int) $_GET['id'];
$type = 'tv';
$actualTvPage = $tmdb->getTVShow($id);
if(empty($actualTvPage->getName()))
{
    header('location: https://' . $_SERVER["HTTP_HOST"] . '/panel/popular?type=tv', true, 301);
    exit;
}


require 'views/headerView.php';
require 'views/topbarView.php';
require 'views/rightbarView.php';
require 'views/leftbarView.php';
require 'views/tv/contentView.php';
$additionalFooterContent = '

<script type="text/javascript">
    jQuery(document).ready(function($) {
      $(".identificationsPost").append(\'<div><input type="hidden" name="typeIdent11" id="typeIdent11" value="tv"/><input type="hidden" name="idIdent11" id="idIdent11" value="' . $actualTvPage->getid() . '"/><a href="https://watchover.prolix.fr/panel/profil?id=' . $actualTvPage->getid() . '" target="_blank" class="badge badge-pill badge-primary text-white">@' . str_replace("'", '&#39;',htmlentities($actualTvPage->getName())) . '</a></div>\');
    });
</script>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $( ".seasonWrapper" ).on( "click", ".collapseButtonImage" , function(e) {
        $("#gallery" + $(this).attr("id")).nanogallery2("refresh");
        console.log("refresh #gallery" + $(this).attr("id"));
      });
    });
</script>
';
require 'views/footer.php';
?>
