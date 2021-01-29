using System.Collections;
using System.Collections.Generic;
using UnityEngine;
using UnityEngine.UI;
using EZCameraShake;

public class Player : MonoBehaviour
{
    [Header("Speed")]
    [Header("CAPACITY :")]
    public float speed = 350;
    public float accelerationForce = 0.05f;
    private float startAccelerationForce;


    [Header("Jump")]
    public float jumpForce = 600;
    public float jumpDelay = 0.2f;
    private float jumpTimer;


    [Header("Attack")]
    public float attackRange = 0.2f;
    public float attackDammage = 100f;
    public float timeAttackActivation = 1f;


    [Header("WallSlide")]
    public float jumpWallXForce = 200f;
    public float wallSlidindSpeed;


    [Header("Dash")]
    public float speedDash;
    public float speedDashY = 10f;
    public float dashTime;
    public float timeDashActivation = 1f;


    [Header("Health")]
    public float maxHealth = 100f;
    public Color hurtColor;


    [Header("AttackSpecial")]
    public float timeAttackSpecialActivation = 2f;


    [Header("CapacitySpecial")]
    public float timeCapacitySpecialActivation = 3f;


    [Header("Particle")]
    public ParticleSystem particleDust;
    public ParticleSystem particleDash;
    public ParticleSystem particleLand;
    public ParticleSystem particleAttack;
    public ParticleSystem particleHit;
    public ParticleSystem particleSlideWall;


    [Header("GameObject")]
    public LayerMask collisionLayers;
    public LayerMask enemiesLayers;
    protected Rigidbody2D rigidbody2D;
    protected Animator animator;
    protected SpriteRenderer spriteRenderer;
    public Transform groundCheck;
    public Transform frontCheck;
    public Transform attackCheck;
    protected float groundCheckRadius = 0.01f;

    [Header("UI")]
    public Slider healthBar;
    public Image fillHealthBar;
    public Gradient gradient;

    public RectTransform imageDash;
    public RectTransform imageAttack;
    public RectTransform imageSpecialAttack;
    public RectTransform imageSpecialCapacity;
    

    
    protected bool isJump = false;
    protected bool isGround = true;
    protected bool WasGround = true;
    protected bool isDoubleJump = false;
    protected bool isDash;
    protected bool isAttack = false;
    protected bool isWallJumping = false;
    protected bool isWallSliding = false;
    protected bool isTouchingFront = false;
    protected bool isFlip = false;

    protected bool canMove = true;
    protected bool canDash = true;
    protected bool canAttack = true;
    protected bool canAttackSpecial = true;
    protected bool canCapacitySpecial = true;

    protected string namePlayer = "";
    protected float currentHealth = 1;
    protected float airTime = 0f;
    protected float startDashTime;

    protected Vector3 velocity = Vector3.zero;
    protected Vector3 posStart;




    void Start()
    {
        this.rigidbody2D = GetComponent<Rigidbody2D>();
        this.spriteRenderer = GetComponent<SpriteRenderer>();

        this.rigidbody2D.isKinematic = false;
        this.rigidbody2D.collisionDetectionMode = CollisionDetectionMode2D.Continuous;
        this.animator = GetComponent<Animator>();

        this.posStart = this.transform.position;
        this.playerSetup();
        this.posStart = new Vector3(this.transform.position.x, 0f, this.transform.position.z);
    }
    public void playerSetup()
    {
        this.transform.position = this.posStart;
        this.rigidbody2D.simulated = true;
        this.rigidbody2D.velocity = new Vector2(0f, 0f);
        startAccelerationForce = accelerationForce;
        currentHealth = maxHealth;
        healthBar.value = currentHealth / maxHealth;
        fillHealthBar.color = gradient.Evaluate(healthBar.value);
    }




    void Update()
    {
        this.SetButton();
    }

    public void FixedUpdate()
    {
        isTouchingFront = Physics2D.OverlapCircle(frontCheck.position, groundCheckRadius, collisionLayers);
        isGround = Physics2D.OverlapCircle(groundCheck.position, groundCheckRadius, collisionLayers);
        CheckLand();
        CheckAirTime();
        Sliding();
       

        this.Move();
        this.flip(rigidbody2D.velocity.x);


        animator.SetFloat("Speed", Mathf.Abs(rigidbody2D.velocity.x));
        animator.SetBool("IsFly", !isGround);
        animator.SetBool("IsWallSlide", isWallSliding);
    }




    public void Move()
    {
        if(!canMove)return;

        if(this.isDash)
        {
            this.Dash();
            return;
        }

        //float horizontalMovement = CrossPlatformInputManager.GetAxis("Horizontal") * speed * Time.deltaTime;
        float horizontalMovement = Input.GetAxis("Horizontal") * speed * Time.deltaTime;
        //Debug.Log(horizontalMovement + "," + CrossPlatformInputManager.GetAxis("Horizontal") + "," + speed + "," + Time.deltaTime);
        Vector3 targetVelocity = new Vector2(horizontalMovement, rigidbody2D.velocity.y);
        rigidbody2D.velocity = Vector3.SmoothDamp(rigidbody2D.velocity, targetVelocity, ref velocity, accelerationForce);

        Jump();
    }






#region Attack

    public void Attack()
    {
        if(!canAttack)return;

        this.isAttack = true;
        animator.SetTrigger("IsAttack");
        Particle("Attack");

        Collider2D[] hitsEnemies = Physics2D.OverlapCircleAll(attackCheck.position, attackRange, enemiesLayers);

        foreach (Collider2D enemy in hitsEnemies)
        {
            //Debug.Log("We hit enemy");
            enemy.GetComponent<Enemy>().TakeDammage(attackDammage);
        }
        StartCoroutine(TimerAttack());
    }

    IEnumerator TimerAttack()
    {
        canAttack = false;
        imageAttack.localScale = new Vector3(0f, 0f, 1f);
        float time = 0f;
        while(time < timeAttackActivation)
        {
            time += Time.deltaTime;
            imageAttack.localScale = new Vector3(time / timeAttackActivation, time / timeAttackActivation, 1f);
            yield return null;
        }
        canAttack = true;
        imageAttack.localScale = new Vector3(1f, 1f, 1f);
    }

#endregion
    
#region JumpSliding


    public void Jump()
    {
        if (this.isJump && (this.isGround || !isDoubleJump || (isWallSliding && !isWallJumping) || jumpTimer > 0f))
        {
            rigidbody2D.velocity = new Vector2(rigidbody2D.velocity.x, 0f);
            rigidbody2D.AddForce(new Vector2(0f, jumpForce));

            if(!this.isGround && !(jumpTimer > 0f))
            {
                if(isWallSliding && !isWallJumping)
                {
                    isWallJumping = true;
                    float coefDir = isFlip ? 1 : -1;
                    rigidbody2D.velocity = Vector2.zero;
                    rigidbody2D.AddForce(new Vector2(jumpWallXForce*coefDir, 0f));
                }
                else
                {
                    isDoubleJump = true;
                }
            }

            jumpTimer = 0f;
            animator.SetTrigger("IsJump");
            this.Particle("Dust");
            //jumpSound.Play();
        }
        this.isJump = false;
    }

    private void CheckAirTime() 
    {
        if (isGround)
        {
            airTime = 0f;
            isDoubleJump = false;
            jumpTimer = jumpDelay;
        }
        else
        {
            airTime += Time.deltaTime;
            jumpTimer -= Time.deltaTime;
        }
    }
    private void CheckLand()
    {
        if (airTime > 0)
        {
            if (isGround)
            {
                Particle("Land");
                animator.SetTrigger("IsLand");
            }
        }
    }

    public void Sliding()
    {
        if(isTouchingFront && !isGround)
        {
            if(!isWallSliding){
                canAttack = false;
                animator.SetTrigger("WallSlide");
                accelerationForce = 0f;
            }
            isWallSliding = true;
            Particle("SlideWall");
        }
        else
        {
            if(isWallSliding){
                canAttack = true;
                accelerationForce = startAccelerationForce;
            }
            isWallSliding = false;
        }

        if(isWallSliding)
        {
            rigidbody2D.velocity = new Vector2(0f, Mathf.Clamp(rigidbody2D.velocity.y, -wallSlidindSpeed, float.MaxValue));
        }
        else
        {
            isWallJumping = false;
        }
    }

#endregion

#region Dash
    public void Dash()
    {
        if(startDashTime < dashTime)
        {
            startDashTime += Time.deltaTime;
            float coefDir = isFlip ? -speedDash : speedDash;
            rigidbody2D.velocity = new Vector2(coefDir, speedDashY);
        }
        else
        {
            startDashTime = 0f;
            isDash = false;
            //rigidbody2D.velocity = Vector2.zero;
        }
    }

        IEnumerator TimerDash()
    {
        canDash = false;
        imageDash.localScale = new Vector3(0f, 0f, 1f);
        float time = 0f;
        while(time < timeDashActivation)
        {
            time += Time.deltaTime;
            imageDash.localScale = new Vector3(time / timeDashActivation, time / timeDashActivation, 1f);
            yield return null;
        }
        canDash = true;
        imageDash.localScale = new Vector3(1f, 1f, 1f);
    }

#endregion
    
#region Health

    public void TakeDammage(float dammage)
    {
        currentHealth -= dammage;

        if(currentHealth <= 0f)
        {
            //Die();
        }

        Particle("Hit");
        StartCoroutine(Flash());
        healthBar.value = currentHealth / maxHealth;
        fillHealthBar.color = gradient.Evaluate(healthBar.value);
    }

    IEnumerator Flash()
    {
        spriteRenderer.color = hurtColor;
        yield return new WaitForSeconds(0.1f);
        spriteRenderer.color = Color.white;
    }

#endregion
    
#region AttackSpecial

    public void AttackSpecial()
    {
        if(!canAttackSpecial)return;

        StartCoroutine(TimerAttackSpecial());
    }

    virtual protected IEnumerator TimerAttackSpecial()
    {
        canAttackSpecial = false;
        canMove = false; canAttack = false; canDash = false; canCapacitySpecial = false;
        rigidbody2D.velocity = Vector2.zero;
        rigidbody2D.AddForce(new Vector2(0f, jumpForce));
        yield return new WaitForSeconds(0.3f);
        while(!isGround)
        {
            yield return null;
        }
        CameraShaker.Instance.ShakeOnce(8f, 8f, 1f, 4f);
        canMove = true; canAttack = true; canDash = true; canCapacitySpecial = true;


        imageSpecialAttack.localScale = new Vector3(0f, 0f, 1f);
        float time = 0f;
        while(time < timeAttackSpecialActivation)
        {
            time += Time.deltaTime;
            imageSpecialAttack.localScale = new Vector3(time / timeAttackSpecialActivation, time / timeAttackSpecialActivation, 1f);
            yield return null;
        }
        canAttackSpecial = true;
        imageSpecialAttack.localScale = new Vector3(1f, 1f, 1f);
    }

#endregion

#region CapacitySpecial

    public void CapacitySpecial()
    {
        if(!canCapacitySpecial)return;

        StartCoroutine(TimerCapacitySpecial());
    }

    IEnumerator TimerCapacitySpecial()
    {
        canCapacitySpecial = false;
        canMove = false; canAttack = false; canDash = false; canAttackSpecial = false;
        rigidbody2D.velocity = Vector2.zero;
        rigidbody2D.AddForce(new Vector2(0f, jumpForce * 1.5f));
        yield return new WaitForSeconds(0.2f);
        rigidbody2D.velocity = Vector2.zero;
        canMove = true; canAttack = true; canDash = true; canAttackSpecial = true;

        rigidbody2D.gravityScale = 0.5f;
        yield return new WaitForSeconds(1f);
        rigidbody2D.gravityScale = 4f;

        imageSpecialCapacity.localScale = new Vector3(0f, 0f, 1f);
        float time = 0f;
        while(time < timeCapacitySpecialActivation)
        {
            time += Time.deltaTime;
            imageSpecialCapacity.localScale = new Vector3(time / timeCapacitySpecialActivation, time / timeCapacitySpecialActivation, 1f);
            yield return null;
        }
        canCapacitySpecial = true;
        imageSpecialCapacity.localScale = new Vector3(1f, 1f, 1f);
    }

#endregion




    public void flip(float _velocity)
    {
        if ((_velocity > 0.1f && isFlip == true) || (_velocity < -0.1f && isFlip == false))
        {
            isFlip = !isFlip;
            transform.localScale = new Vector3(-transform.localScale.x, transform.localScale.y, transform.localScale.z);
            if(isGround)
            {
                Particle("Dust");
            }
        }
    }



    public void Particle(string type)
    {
        float coefDir;
        ParticleSystemRenderer psr;
        switch (type)
        {
            case "Dust" :
                particleDust.Play();
                break;

            case "Dash" :
                coefDir = isFlip ? 1 : 0;
                psr = particleDash.GetComponent<ParticleSystemRenderer>();
                psr.flip = new Vector3(coefDir, 0f, 0f);
                particleDash.Play();
                CameraShaker.Instance.ShakeOnce(8f, 8f, 0.1f, 0.1f);
                break;

            case "Land" :            
                particleLand.Play();
                break;

            case "Hit" :            
                particleHit.Play();
                break;

            case "SlideWall" :  
                coefDir = isFlip ? -1 : 1;
                var shS = particleSlideWall.shape;
                shS.position = new Vector3(coefDir*0.25f, 0.5f, 0f);        
                particleSlideWall.Play();
                break;

            case "Attack" :
                coefDir = isFlip ? -1 : 1;
                var shA = particleAttack.shape;
                shA.position = new Vector3(coefDir, 0f, 0f);
                particleAttack.Play();
                break;

            default:
                Debug.Log("Not Particle for this name : " + type);
                break;
        }
    }



    public void SetButton()
    {
         if (Input.GetButtonDown("Jump"))
        {
            this.isJump = true;
        }
        if(Input.GetKeyDown("a"))
        {
            if(!canDash)return;

            this.isDash = true;

            StartCoroutine(TimerDash());
            animator.SetTrigger("IsDash");
            Particle("Dash");
        }
        if (Input.GetKeyDown("q"))
        {
            Attack();
        }
        if (Input.GetKeyDown("c"))
        {
            AttackSpecial();
        }
        if (Input.GetKeyDown("d"))
        {
            CapacitySpecial();
        }

        /*if (CrossPlatformInputManager.GetButtonDown("Jump"))
        {
            this.isJump = true;
        }*/
    }

}
