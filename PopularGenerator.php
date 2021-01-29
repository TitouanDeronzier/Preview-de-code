<?php
require_once '/home/prolixp/www/watchover/panel/controleurs/initControler.php';
require_once '/home/prolixp/www/watchover/panel/controleurs/accountsControler.php';
require_once '/home/prolixp/www/watchover/panel/controleurs/panelAdditionalInitControler.php';

// A generator of popular media(movie, tv, actor) for WathOver


$page = (int) $_GET['page'];
if(empty($page)){
    $page = 1;
}


$type = htmlspecialchars($_GET['type']);
switch($type){
    case "movie" :
        $populars = $tmdb->getPopularMovies($page);
        $link= 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/default-movie.jpg';
        break;
    case "person" :
        $populars = $tmdb->getPopularPersons($page);
        $link= 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/user-default.jpg';
        break;
    case "tv" :
        $populars = $tmdb->getPopularTVShows($page);
        $link= 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/default-serie.png';
        break;
    default:
        echo 'error';
        exit;
}

foreach($populars as $popular){

    switch($type){
        case "movie" :
            $popularTitle = $popular->getTitle();
            if(!empty($popular->getPoster())){
                $link = $tmdb->getImageURL('w500') . $popular->getPoster();
            }
            $date = $popular->get('release_date');
            break;
        case "person" :
            $popularTitle = $popular->getName();
            if(!empty($popular->getProfile())){
                $link = $tmdb->getImageURL('w500') . $popular->getProfile();
            }
            $date = '';
            break;
        case "tv" :
            $popularTitle = $popular->getName();
            if(!empty($popular->getPoster())){
                $link = $tmdb->getImageURL('w500') . $popular->getPoster();
            }
            $date = $popular->get('first_air_date');
            break;
        default:
            echo 'error';
            exit;
    }
    $id = $popular->getId();
    //$dateRelease = new DateTime($popular->get('release_date'));
    echo '
                        <li>
                        <div class="f-page">
                            <figure style="height: 300px; background: url('. $link . ') center no-repeat; background-size: cover;">
                                
                                <div class="dropdown pgs">
								                   <form class="followContentForm mx-auto w-100">
                                        <input type="hidden" name="idMedia" value="' . $id . '">
                                        <input type="hidden" name="typeMedia" value="' . $type . '">';
    if (!$_user->isFollower($id ,$type)) {
        echo '
                                        <input type="hidden" name="actionFollowContent" value="startFollowing">
                                        <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-plus text-primary"></i> Suivre </button>'; }
    else{
        echo  '
                                        <input type="hidden" name="actionFollowContent" value="stopFollowing">
                                        <button class="btn btn-outline-danger w-100" type="submit"><i class="fas fa-minus text-danger"></i> Arreter de suivre </button>';} echo '
                                    </form>
                                </div>
                                <em>'. $popular->getNbFollowers() .' followers</em>
                            </figure>
                            <div class="page-infos">
                                <h5><a href="' . $popular->getlink() . '" title="">' . $popularTitle . '</a></h5>
                                <span>' . $date . '</span>
                            </div>
                        </div>
                    </li>
                        ';
}

