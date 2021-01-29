<?php
require_once "/home/prolixp/www/watchover/panel/controleurs/initControler.php";
require_once "/home/prolixp/www/watchover/panel/models/User.php";
require_once "/home/prolixp/www/watchover/panel/models/PermissionGroup.php";

//Permission of a user , add,test or delete permission from a user

class Permission
{
    private $_nom;
    private $_description;
    private $_db;
    private $_id;

    public function __construct(string $nom)
    {
        global $dbUsers;
        $this->_db = $dbUsers;
        $this->_nom = htmlspecialchars($nom);
        $this->_description = '';
        $this->_id = -1;
        if(!empty($nom) && $this->exists()){
            $this->load();
        }
    }

    public function create(string $desc, bool $default): array
    {
        $this->_description = htmlspecialchars($desc);
        if ($this->exists()) {
            return array('state' => 0, 'message' => 'La permission existe deja');
        }
        if (strlen($this->_nom) < 3){
            return array('state' => 0, 'message' => 'La permission doit faire 3 caractéres minimum.');
        }
        $req = $this->_db->prepare('INSERT INTO permissions(nom, description) VALUES(?, ?)');
        $req->execute(array($this->_nom, $this->_description));
        $req = $this->_db->prepare('SELECT id FROM permissions WHERE nom = ?');
        $req->execute(array($this->_nom));
        $data = $req->fetch();
        $this->_id = $data[0];

        if($default){
            $defGroup = new PermissionGroup('user');
            $this->giveToGroup($defGroup);
        }

        return array('state'=>1, 'message'=>'Permission <b>' . $this->_nom . '</b> (' . $this->_description . ') créee avec defaut ' . $default);
    }

    public function exists() : bool
    {
        $req = $this->_db->prepare('SELECT COUNT(id) FROM permissions WHERE nom = ?');
        $req->execute(array($this->_nom));
        $data = $req->fetch();
        return $data[0] > 0;
    }


    public function load(): array
    {
        if (!$this->exists()) {
            return array('state' => 0, 'message' => 'La permission n\'existe pas');
        }

        $req = $this->_db->prepare('SELECT * FROM permissions WHERE nom = ?');
        $req->execute(array($this->_nom));
        $data = $req->fetch();
        $this->_id = $data['id'];
        $this->_description = $data['description'];
        return array('state' => 1, 'message' => 'La description est : ' . $this->_description);
    }

    public function delete(): array
    {
        if (!$this->exists()) {
            return array('state' => 0, 'message' => 'La permission n\'existe pas');
        }
        $this->load();
        $req = $this->_db->prepare('DELETE FROM permissions WHERE id = ?'); // La description, la présence dans des groupes et dans des utilisateurs sera automatiquement supprimé par la base de données grace au foreign keys en cascade (on supprime la mère, ca suprime toutes les lignes ou telle est présente dans les autres tables)
        $req->execute(array($this->_id));
        return array('state' => 1, 'message' => 'La permission <b>' . $this->_nom . '</b> à bien été supprimée.');
    }

    //todo : groupHave(PermissionGroup), GiveToGroup(...), takeToGroup(...)



    public function giveToUser(User $user){
        if(!$this->exists()){return 0;}
        if($this->_id === null){
            $this->load();
        }
        $req = $this->_db->prepare('INSERT INTO permissionsUsers(id_perm, id_user) VALUES(?,?)');
        $req->execute(array($this->_id, $user->getId()));
        return 'La permission ' . $this->_nom . ' à été donnée à l\'utilisateur ' . $user->getPseudo() . '(' . $user->getId() .').' ;
    }

    public function userHave(User $user){
        if(!$this->exists()){return 0;}
        if($this->_id === null){
            $this->load();
        }
        $req = $this->_db->prepare('SELECT count(id_user) FROM permissionsUsers WHERE id_perm = ? AND id_user = ?');
        $data = $req->execute(array($this->_id, $user->getId()));
        return $data >= 1;
    }

    public function takeToUser(User $user){
        if(!$this->exists()){return 0;}
        if($this->_id === null){
            $this->load();
        }
        $req = $this->_db->prepare('DELETE FROM permissionsUsers WHERE id_perm = ? AND id_user = ?');
        $req->execute(array($this->_id, $user->getId()));
        return 'La permission ' . $this->_nom . ' a ete enleve à l\'utilisateur ' . $user->getPseudo() . '(' . $user->getId() .').' ;
    }

    public function giveToGroup(PermissionGroup $group){
        if(!$this->exists()){return 0;}
        if($this->_id === null){
            $this->load();
        }
        $req = $this->_db->prepare('INSERT INTO permissionsGroups(id_perm, id_group) VALUES(?,?)');
        $req->execute(array($this->_id, $group->getId()));
        return 'La permission ' . $this->_nom . ' à été donnée à l\'utilisateur ' . $group->getNom() . '(' . $group->getId() .').' ;
    }

    public function groupHave(PermissionGroup $group){
        if(!$this->exists()){return 0;}
        if($this->_id === null){
            $this->load();
        }
        $req = $this->_db->prepare('SELECT count(id_groups) FROM permissionsGroups WHERE id_perm = ? AND id_groups = ?');
        $data = $req->execute(array($this->_id, $group->getId()));
        return $data >= 1;
    }

    public function takeToGroup(PermissionGroup $group){
        if(!$this->exists()){return 0;}
        if($this->_id === null){
            $this->load();
        }
        $req = $this->_db->prepare('DELETE FROM permissionsUsers WHERE id_perm = ? AND id_group = ?');
        $req->execute(array($this->_id, $group->getId()));
        return 'La permission ' . $this->_nom . ' a ete enleve à l\'utilisateur ' . $group->getNom() . '(' . $group->getId() .').' ;
    }

    public function getDescription() : string
    {
        if($this->_description === ''){
            $this->load();
        }
        return $this->_description;
    }

    public function getNom(): string
    {
        return $this->_nom;
    }

    public function getId() : int
    {
        if($this->_id === null){
            $this->load();
        }
        return $this->_id;
    }
}
