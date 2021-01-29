<?php
require_once '/home/prolixp/www/watchover/panel/controleurs/panelAdditionalInitControler.php';

//Playlist utilateurs (small preview to manage playlist in WatchOver
class Playlist
{
    private $_id_owner;
    private $_id;
    private $_name;
    private $date;
    private $_idMedias;

    private $_db;


    public function __construct(User $owner, $id = 0)
    {
        global $dbUsers;
        $this->_db = $dbUsers;
        $this->_owner = $owner;
        $this->_id_owner = $owner->getId();
        $this->_id = $id;
        $this->_name = '';
        $this->_date = new DateTime();
        $this->_idMedias = array();
        $this->_idMedias['movie'] = array();
        $this->_idMedias['tv'] = array();
        $this->_idMedias['person'] = array();

        if ($id > -1 && $this->exists()) {
            $this->load();
        } else {
            $this->_id = -1;
        }

    }

    public function exists(): bool
    {
        $req = $this->_db->prepare('SELECT id FROM playlists WHERE id = ?');
        $req->execute(array($this->_id));
        $data = $req->fetch();
        return !empty($data);
        //return $data[0] > 0;
    }

    public function load(): array
    {
        if (!$this->exists()) {
            return array('state' => 0, 'message' => 'La playlist n\'existe pas d id = ' . $this->_id);
        }
        $req = $this->_db->prepare('SELECT * FROM playlists WHERE id = ?');
        $req->execute(array($this->_id));
        $data = $req->fetch();
        $this->_id_owner = $data['id_owner'];
        $this->_name = $data['name'];
        $this->_date = new DateTime($data['dateCreation']);

        $req = $this->_db->prepare('SELECT id_media, type_media  FROM playlistsContent where id_playlist IN(SELECT id FROM playlists where id = ?)');
        $req->execute(array($this->_id));
        while ($data = $req->fetch()) {
            $this->_idMedias[$data['type_media']][] = $data['id_media'];
        }
        return array('state' => 1, 'message' => 'Post loaded successfully');
    }

    public function create()
    {
        if (empty($this->_id_owner)) {
            return array('state' => 0, 'message' => 'User non set');
        }
        if (empty($this->_name)) {
            return array('state' => 0, 'message' => 'Name non set');
        }

        if (!empty($this->_owner->getPlaylists())) {
            if (count($this->_owner->getPlaylists()) >= 20) {
                return array('state' => 0, 'message' => 'Deja plus de 20 playlist');
            }
        }


        $req = $this->_db->prepare("INSERT INTO playlists(id_owner,name) VALUES(?,?)");
        $req->execute(array($this->_id_owner, $this->_name));
        $this->_id = $this->_db->lastInsertId();
        $this->_date = new DateTime();

        foreach ($this->_idMedias['movie'] as $movie) {
            $req = $this->_db->prepare("INSERT INTO playlistsContent(id_playlist,id_media,type_media) VALUES(?,?,'movie')");
            $req->execute(array($this->_id, $movie));
        }
        foreach ($this->_idMedias['tv'] as $movie) {
            $req = $this->_db->prepare("INSERT INTO playlistsContent(id_playlist,id_media,type_media) VALUES(?,?,'tv')");
            $req->execute(array($this->_id, $movie));
        }
        foreach ($this->_idMedias['person'] as $movie) {
            $req = $this->_db->prepare("INSERT INTO playlistsContent(id_playlist,id_media,type_media) VALUES(?,?,'person')");
            $req->execute(array($this->_id, $movie));
        }


        return array('state' => 1, 'message' => $this->getHTMLPreviewDelete());

    }

    public function delete(): array
    {
        if (!$this->exists()) {
            return array('state' => 0, 'message' => 'La playlist n\'existe pas');
        }
        //
        $req = $this->_db->prepare('DELETE FROM playlistsContent WHERE id_playlist = ?');
        $req->execute(array($this->_id));
        $req = $this->_db->prepare('DELETE FROM playlists WHERE id = ?');
        $req->execute(array($this->_id));
        return array('state' => 1, 'message' => 'La playlist a bien été supprimé');
    }


    public function getMedia($id_media, $type_media): bool
    {
        if ($type_media !== 'movie' && $type_media !== 'tv' && $type_media !== 'person') {
            return false;
        }

        $req = $this->_db->prepare('SELECT id_playlist  FROM playlistsContent where id_playlist = ? AND id_media = ? AND type_media = ?');
        $req->execute(array($this->_id, $id_media, $type_media));
        $data = $req->fetch();
        return (!empty($data));
    }

    public function addMedia($id_media, $type_media): array
    {
        if ($type_media !== 'movie' && $type_media !== 'tv' && $type_media !== 'person') {
            return array('state' => 0, 'message' => 'Ce type de media n existe pas : ' . $type_media);
        }
        $this->_idMedias[$type_media][] = $id_media;
        $req = $this->_db->prepare('INSERT INTO playlistsContent(id_playlist, type_media, id_media) VALUES(?,?,?)');
        $req->execute(array($this->_id, $type_media, $id_media));
        return array('state' => 1, 'message' => 'Ce media a bien été ajouté');
    }

    public function deleteMedia($id_media, $type_media): array
    {
        if ($type_media !== 'movie' && $type_media !== 'tv' && $type_media !== 'person') {
            return array('state' => 0, 'message' => 'Ce type de media n existe pas');
        }

        foreach ($this->_idMedias[$type_media] as $media) {
            if ((int)$media === $id_media) {
                //unset($this->_idMedias[$type_media][$media]);
                $req = $this->_db->prepare('DELETE FROM playlistsContent WHERE id_playlist = ? AND type_media = ? AND id_media = ?');
                $req->execute(array($this->_id, $type_media, $id_media));
                return array('state' => 1, 'message' => 'Ce media a bien été supprime');
            }
        }

        return array('state' => 1, 'message' => 'Ce media n existe pas dans la playlist');
    }

    //get the preview html code of this playlist (for the user profil)
    public function getHTMLPreview($_userCurrent)
    {
        global $tmdb;
        $size = count($this->_idMedias['movie']) + count($this->_idMedias['tv']) + count($this->_idMedias['person']);
        $link = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/default-serie.png';
        if (!empty($this->_idMedias['movie'][0])) {
            $movie = $tmdb->getMovie((int)$this->_idMedias['movie'][0]);
            if (!empty($movie->getPoster())) {
                $link = $tmdb->getImageURL('w500') . $movie->getPoster();
            }
        } else if (!empty($this->_idMedias['tv'][0])) {
            $tv = $tmdb->getTvShow((int)$this->_idMedias['tv'][0]);
            if (!empty($tv->getPoster())) {
                $link = $tmdb->getImageURL('w500') . $tv->getPoster();
            }
        } else if (!empty($this->_idMedias['person'][0])) {
            $person = $tmdb->getPerson((int)$this->_idMedias['person'][0]);
            if (!empty($person->getProfile())) {
                $link = $tmdb->getImageURL('w500') . $person->getProfile();
            }
        }

        $linkMedia = "#";
        if (!$_userCurrent->isFollower($this->_id, 'playlist')) {
            $button = '
                    <input type="hidden" name="actionFollowContent" value="startFollowing">
                    <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-plus text-primary"></i> Suivre </button>';
        } else {
            $button = '
                    <input type="hidden" name="actionFollowContent" value="stopFollowing">
                    <button class="btn btn-outline-danger w-100" type="submit"><i class="fas fa-minus text-danger"></i> Arreter de suivre </button>';
        }


        return '
                <li>
                    <div class="f-page">
                        <figure style="height: 300px; background: url(' . $link . ') center no-repeat; background-size: cover;">
                            <div class="dropdown pgs">
                                <form class="displayPlaylist" id="displayPlaylist">
                                    <button class="btn btn-warning" type="submit">
                                        <i class="fas fa-plus"></i> Voir plus
                                    </button>
                                    <input id="idPlaylist" name="idPlaylist" type="hidden" value="' . $this->_id . '">
                                </form>
                                <form class="followContentForm mx-auto w-100">
                                    <input type="hidden" name="idMedia" value="' . $this->_id . '">
                                    <input type="hidden" name="typeMedia" value="playlist">
                                    ' . $button . '
                                </form>
                            </div>
                            <em></em>
                        </figure>
                        <div class="page-infos">
                            <h5><a title="">' . $this->_name . '</a></h5>
                            <span>' . $this->_date->format('j/n/Y') . ' | Taille : ' . $size . '</span>
                        </div>
                    </div>
                </li>';
    }

    //get the preview html code of this playlist (for the user profil who was connected)
    public function getHTMLPreviewDelete()
    {
        global $tmdb;
        $size = count($this->_idMedias['movie']) + count($this->_idMedias['tv']) + count($this->_idMedias['person']);
        $link = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/default-serie.png';
        if (!empty($this->_idMedias['movie'][0])) {
            $movie = $tmdb->getMovie((int)$this->_idMedias['movie'][0]);
            if (!empty($movie->getPoster())) {
                $link = $tmdb->getImageURL('w500') . $movie->getPoster();
            }
        } else if (!empty($this->_idMedias['tv'][0])) {
            $tv = $tmdb->getTvShow((int)$this->_idMedias['tv'][0]);
            if (!empty($tv->getPoster())) {
                $link = $tmdb->getImageURL('w500') . $tv->getPoster();
            }
        } else if (!empty($this->_idMedias['person'][0])) {
            $person = $tmdb->getPerson((int)$this->_idMedias['person'][0]);
            if (!empty($person->getProfile())) {
                $link = $tmdb->getImageURL('w500') . $person->getProfile();
            }
        }

        $linkMedia = "#";


        return '
                    <li class="deleteLi' . $this->_id . '" id="deleteLi' . $this->_id . '">
                        <div class="f-page">
                            <figure style="height: 300px; background: url(' . $link . ') center no-repeat; background-size: cover;">

                                <div class="dropdown pgs">
                                    <form class="displayPlaylist" id="displayPlaylist">
                                        <button class="btn btn-warning" type="submit">
                                            <i class="fas fa-plus"></i> Voir plus
                                        </button>
                                        <input id="idPlaylist" name="idPlaylist" type="hidden" value="' . $this->_id . '">
                                    </form>
                                    <form class="deletePlaylist" id="deletePlaylist">
                                        <button class="btn btn-danger" type="submit">
                                            <i class="fas fa-plus"></i> Supprimer
                                        </button>
                                        <input id="idPlaylist" name="idPlaylist" type="hidden" value="' . $this->_id . '">
                                    </form>
								</div>
                                <em></em>
                            </figure>
                            <div class="page-infos">
                                <h5><a title="">' . $this->_name . '</a></h5>
                                <span>' . $this->_date->format('j/n/Y') . ' | Taille : ' . $size . '</span>
                            </div>
                        </div>
                    </li>';
    }

    //get the preview html code of this playlist (for the user profil) with all items of this playlist
    public function getHTML($_userCurrent)
    {
        global $tmdb;
        $res = '';
        if (!empty($this->_idMedias['movie'][0])) {
            $link = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/default-movie.jpg';
            foreach ($this->_idMedias['movie'] as $m) {
                $media = $tmdb->getMovie((int)$m);
                $title = $media->getTitle();
                if (!empty($media->getPoster())) {
                    $linkE = $tmdb->getImageURL('w500') . $media->getPoster();
                } else {
                    $linkE = $link;
                }
                $date = $media->get('release_date');
                $id = $media->get('id');
                $res .= '<li>
                        <div class="f-page">
                            <figure style="height: 300px; background: url(' . $linkE . ') center no-repeat; background-size: cover;">

                                <div class="dropdown pgs">
									<form class="followContentForm mx-auto w-100">
                                        <input type="hidden" name="idMedia" value="' . $id . '">
                                        <input type="hidden" name="typeMedia" value="movie">';
                if (!$_userCurrent->isFollower($id, "movie")) {
                    $res .= '
                                        <input type="hidden" name="actionFollowContent" value="startFollowing">
                                        <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-plus text-primary"></i> Suivre </button>';
                } else {
                    $res .= '
                                        <input type="hidden" name="actionFollowContent" value="stopFollowing">
                                        <button class="btn btn-outline-danger w-100" type="submit"><i class="fas fa-minus text-danger"></i> Arreter de suivre </button>';
                }
                $res .= '
                                    </form>
                                </div>
                                <em>' . $media->getNbFollowers() . ' followers</em>
                            </figure>
                            <div class="page-infos">
                                <h5><a href="' . $media->getlink() . '" title="">' . $title . '</a></h5>
                                <span>' . $date . '</span>
                            </div>
                        </div>
                    </li>';
            }
        }

        if (!empty($this->_idMedias['tv'][0])) {
            $link = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/default-serie.png';
            foreach ($this->_idMedias['tv'] as $m) {
                $media = $tmdb->getTvShow((int)$m);
                $title = $media->getName();
                if (!empty($media->getPoster())) {
                    $linkE = $tmdb->getImageURL('w500') . $media->getPoster();
                } else {
                    $linkE = $link;
                }
                $date = $media->get('first_air_date');
                $id = $media->get('id');
                $res .= '<li>
                        <div class="f-page">
                            <figure style="height: 300px; background: url(' . $linkE . ') center no-repeat; background-size: cover;">

                                <div class="dropdown pgs">
									<form class="followContentForm mx-auto w-100">
                                        <input type="hidden" name="idMedia" value="' . $id . '">
                                        <input type="hidden" name="typeMedia" value="tv">';
                if (!$_userCurrent->isFollower($id, "tv")) {
                    $res .= '
                                        <input type="hidden" name="actionFollowContent" value="startFollowing">
                                        <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-plus text-primary"></i> Suivre </button>';
                } else {
                    $res .= '
                                        <input type="hidden" name="actionFollowContent" value="stopFollowing">
                                        <button class="btn btn-outline-danger w-100" type="submit"><i class="fas fa-minus text-danger"></i> Arreter de suivre </button>';
                }
                $res .= '
                                    </form>
                                </div>
                                <em>' . $media->getNbFollowers() . ' followers</em>
                            </figure>
                            <div class="page-infos">
                                <h5><a href="' . $media->getlink() . '" title="">' . $title . '</a></h5>
                                <span>' . $date . '</span>
                            </div>
                        </div>
                    </li>';
            }
        }

        if (!empty($this->_idMedias['person'][0])) {
            $link = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/user-default.jpg';
            foreach ($this->_idMedias['person'] as $m) {
                $media = $tmdb->getPerson((int)$m);
                $title = $media->getName();
                if (!empty($media->getProfile())) {
                    $linkE = $tmdb->getImageURL('w500') . $media->getProfile();
                } else {
                    $linkE = $link;
                }
                $date = '';
                $id = $media->get('id');
                $res .= '<li>
                        <div class="f-page">
                            <figure style="height: 300px; background: url(' . $linkE . ') center no-repeat; background-size: cover;">

                                <div class="dropdown pgs">
									<form class="followContentForm mx-auto w-100">
                                        <input type="hidden" name="idMedia" value="' . $id . '">
                                        <input type="hidden" name="typeMedia" value="person">';
                if (!$_userCurrent->isFollower($id, "person")) {
                    $res .= '
                                        <input type="hidden" name="actionFollowContent" value="startFollowing">
                                        <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-plus text-primary"></i> Suivre </button>';
                } else {
                    $res .= '
                                        <input type="hidden" name="actionFollowContent" value="stopFollowing">
                                        <button class="btn btn-outline-danger w-100" type="submit"><i class="fas fa-minus text-danger"></i> Arreter de suivre </button>';
                }
                $res .= '
                                    </form>
                                </div>
                                <em>' . $media->getNbFollowers() . ' followers</em>
                            </figure>
                            <div class="page-infos">
                                <h5><a href="' . $media->getlink() . '" title="">' . $title . '</a></h5>
                                <span>' . $date . '</span>
                            </div>
                        </div>
                    </li>';
            }
        }
        if (empty($res)) {
            $res = 'Aucun media n a été ajouté';
        }
        return $res;

    }

    //Get the button to add an item in a playlist
    public function getHTMLAdd($idMedia, $typeMedia)
    {
        global $tmdb;
        $link = 'https://' . $_SERVER['HTTP_HOST'] . '/panel/assets/images/resources/default-serie.png';
        if (!empty($this->_idMedias['movie'][0])) {
            $movie = $tmdb->getMovie((int)$this->_idMedias['movie'][0]);
            if (!empty($movie->getPoster())) {
                $link = $tmdb->getImageURL('w500') . $movie->getPoster();
            }
        } else if (!empty($this->_idMedias['tv'][0])) {
            $tv = $tmdb->getTvShow((int)$this->_idMedias['tv'][0]);
            if (!empty($tv->getPoster())) {
                $link = $tmdb->getImageURL('w500') . $tv->getPoster();
            }
        } else if (!empty($this->_idMedias['person'][0])) {
            $person = $tmdb->getPerson((int)$this->_idMedias['person'][0]);
            if (!empty($person->getProfile())) {
                $link = $tmdb->getImageURL('w500') . $person->getProfile();
            }
        }
        $nameAddplaylistContentId = 'addPlaylistCheckContent' . $this->_id;
        $check = '';
        if ($this->getMedia($idMedia, $typeMedia)) {
            $check = "checked";
        }


        return '
        <li class="list-group-item">
            <div class="container row">
                <div class="col-md col-md-3">
                    <figure style="margin-bottom:0;">
                        <img src="' . $link . '" style="width: 45px; height: 45px; object-fit: cover;" alt="">
                    </figure>
                </div>
                <div class="col-md col-md-7" style="overflow-wrap: anywhere; margin:auto;">
                    <a>' . $this->getName() . '</a>
                </div>
                <div class="col-md col-md-2" style="margin:auto; vertical-align: middle">
                    <span class="custom-control custom-checkbox addPlaylistCheckContent" id="' . $nameAddplaylistContentId . '">
                        <input class="idPlaylist" id="idPlaylist' . $this->_id . '" name="idPlaylist" type="hidden" value="' . $this->_id . '">
                        <input class="idMedia" id="idMedia' . $this->_id . '" name="idMedia" type="hidden" value="' . $idMedia . '">
                        <input class="typeMedia" id="typeMedia' . $this->_id . '" name="typeMedia" type="hidden" value="' . $typeMedia . '">
                        <input class="custom-control-input addPlaylistCheck" type="checkbox" value="" id="check' . $this->_id . '" ' . $check . '>
                        <label class="custom-control-label" for="check' . $this->_id . '"></label>
                    </span>
                </div>
            </div> 
           
        </li>
        <script>    
        $("#' . $nameAddplaylistContentId . '").on("change","#check' . $this->_id . '", function(e){
        let formValues= $(this).serializeArray();
        let checked = $("#check' . $this->_id . '").is(":checked");
        let check = 0;
        if(checked){check = 1;}
        let parent = $(this);
        let idPlaylist = $("#idPlaylist' . $this->_id . '").val();
        let idMedia = $("#idMedia' . $this->_id . '").val();
        let typeMedia = $("#typeMedia' . $this->_id . '").val();
        formValues.push({name: "idPlaylist", value: idPlaylist});
        formValues.push({name: "idMedia", value: idMedia});
        formValues.push({name: "typeMedia", value: typeMedia});
        formValues.push({name: "check", value: check});

        $.ajax({
            type: "GET",
            url: "workers/addPlaylistExec.php",
            data: $.param(formValues),
            contentType: false,
            processData: false,
            success: function (data) {
            console.log(data);
            const returnValue = JSON.parse(data);
            let message = returnValue.message;
            let state = returnValue.state;
                if(state == 0){
                    alert("Une erreur s est produite : " + message);
                }
            }
        });
    });
    </script>';
    }


    /**
     * @return mixed
     */
    public function getIdOwner()
    {
        return $this->_id_owner;
    }

    /**
     * @param mixed $id_owner
     */
    public function setIdOwner($id_owner)
    {
        $this->_id_owner = $id_owner;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->_name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->_name = $name;
    }

    /**
     * @return array
     */
    public function getIdMedias(): array
    {
        return $this->_idMedias;
    }

    /**
     * @param array $idMedias
     */
    public function setIdMedias(array $idMedias)
    {
        $this->_idMedias = $idMedias;
    }

    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->_owner;
    }

    /**
     * @param User $owner
     */
    public function setOwner(User $owner)
    {
        $this->_owner = $owner;
    }
}
