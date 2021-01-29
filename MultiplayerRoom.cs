//Forked and modified from infoGamer at https://www.infogamerhub.com/
using System.Collections;
using System.Collections.Generic;
using Photon.Pun;
using Photon.Realtime;
using UnityEngine;
using UnityEngine.SceneManagement;
using System.IO;

public class PhotonRoom : MonoBehaviourPunCallbacks, IInRoomCallbacks
{
    public static PhotonRoom photonRoom;
    private PhotonView PhotonView;

    public bool isGameLoad;
    public int currentScene;

    Player[] photonPlayers;
    public int playersInRoom;
    public int myNumberInRoom;

    public int playerInGame;

    private bool readyToCount;
    private bool readyToStart;
    public float startingTime;
    private float lessThanMaxPlayers;
    private float atMaxPlayer;
    private float timeToStart;


    private void Awake()
    {
        if(PhotonRoom.photonRoom == null)
        {
            PhotonRoom.photonRoom = this;
        }
        else
        {
            if(PhotonRoom.photonRoom != this)
            {
                Destroy(PhotonRoom.photonRoom.gameObject);
                PhotonRoom.photonRoom = this;
            }
        }
        DontDestroyOnLoad(this.gameObject);
        //PhotonNetwork.AddCallbackTarget(this);
        //UnityEngine.SceneManagement.SceneManager.sceneLoaded += OnSceneFinishedLoading; 
    }

    void Start()
    {
        PhotonView = GetComponent<PhotonView>();
        readyToCount = false;
        readyToStart = false;
        lessThanMaxPlayers = startingTime;
        atMaxPlayer = 6;
        timeToStart = startingTime;
    }

    public override void OnEnable()
    {
        Debug.Log("OnEnable");
        base.OnEnable();
        PhotonNetwork.AddCallbackTarget(this);
        UnityEngine.SceneManagement.SceneManager.sceneLoaded += OnSceneFinishedLoading; 
    }
    public override void OnDisable()
    {
        base.OnDisable();
        PhotonNetwork.RemoveCallbackTarget(this);
        UnityEngine.SceneManagement.SceneManager.sceneLoaded -= OnSceneFinishedLoading;
    }

    void Update()
    {
        if(MultiplayerSettings.multiplayerSettings.delayStart)
        {
            if(playersInRoom == 1)
            {
                RestartTimer();
            }
            if(!isGameLoad)
            {
                if(readyToStart)
                {
                    atMaxPlayer -= Time.deltaTime;
                    lessThanMaxPlayers = atMaxPlayer;
                    timeToStart = atMaxPlayer;
                }
                else if(readyToCount)
                {
                    lessThanMaxPlayers -= Time.deltaTime;
                    timeToStart = lessThanMaxPlayers;
                }
                Debug.Log("Display time to start : " + timeToStart.ToString());
                if(timeToStart <= 0)
                {
                    StartGame();
                }
            }
        }
    }

    public override void OnJoinedRoom()
    {
        base.OnJoinedRoom();
        Debug.Log("Room joined");
        photonPlayers = PhotonNetwork.PlayerList;
        playersInRoom = photonPlayers.Length;
        myNumberInRoom = playersInRoom;
        PhotonNetwork.NickName = myNumberInRoom.ToString();
        if(MultiplayerSettings.multiplayerSettings.delayStart)
        {
            Debug.Log("Disable players in Room out OnFriendListUpdate max players possible");
            if(playersInRoom > 1)
            {
                readyToCount = true;
            }
            if(playersInRoom == MultiplayerSettings.multiplayerSettings.maxPlayers)
            {
                readyToStart = true;
                if(!PhotonNetwork.IsMasterClient)
                    return;
                PhotonNetwork.CurrentRoom.IsOpen = false;
            }
        }
        else
        {
            StartGame();
        }
    }

    public override void OnPlayerEnteredRoom(Player newPlayer)
    {
        base.OnPlayerEnteredRoom(newPlayer);
        Debug.Log("New Player entred in the room");
        photonPlayers = PhotonNetwork.PlayerList;
        if(MultiplayerSettings.multiplayerSettings.delayStart)
        {
            Debug.Log("Disable players in Room out OnFriendListUpdate max players possible");
            if(playersInRoom > 1)
            {
                readyToCount = true;
            }
            if(playersInRoom == MultiplayerSettings.multiplayerSettings.maxPlayers)
            {
                readyToStart = true;
                if(!PhotonNetwork.IsMasterClient)
                    return;
                PhotonNetwork.CurrentRoom.IsOpen = false;
            }
        }
    }

    private void StartGame()
    {
        Debug.Log("GameStart");
        isGameLoad = true;
        if(!PhotonNetwork.IsMasterClient)
            return;
        if(MultiplayerSettings.multiplayerSettings.delayStart)
        {
            PhotonNetwork.CurrentRoom.IsOpen = false;
        }
        PhotonNetwork.LoadLevel(MultiplayerSettings.multiplayerSettings.multiplayerScene);
    }
    private void RestartTimer()
    {
        lessThanMaxPlayers = startingTime;
        timeToStart = startingTime;
        atMaxPlayer = 6;
        readyToCount = false;
        readyToStart = false;
    }

    void OnSceneFinishedLoading(Scene scene, LoadSceneMode mode)
    {
        Debug.Log("Scene load");
        currentScene = scene.buildIndex;
        if(currentScene == MultiplayerSettings.multiplayerSettings.multiplayerScene)
        {
            isGameLoad = true;

            if(MultiplayerSettings.multiplayerSettings.delayStart)
            {
                PhotonView.RPC("RPC_LoadedGameScene", RpcTarget.MasterClient);
            }
            else
            {
                RPC_CreatePlayer();
            }
        }
    }

    [PunRPC]
    private void RPC_LoadingGameScene()
    {
        playerInGame++;
        if(playerInGame == PhotonNetwork.PlayerList.Length)
        {
            photonView.RPC("RPC_CreatePlayer", RpcTarget.All);
        }
    }

    [PunRPC]
    private void RPC_CreatePlayer()
    {
        Debug.Log("Player Intanciate");
        PhotonNetwork.Instantiate(Path.Combine("Multiplayer", "CarMultiplayer"), transform.position, Quaternion.identity, 0);
    }
}
