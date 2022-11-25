<?php

namespace App\Entity;

use App\Repository\PasteRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "string", length: 100, unique: true)]
    private $username;

    #[ORM\Column(type: "json")]
    private $roles = [];

    #[ORM\Column(type: "string", length: 255)]
    private $password;

    #[ORM\Column(type: "string", length: 255)]
    private string $encryptionKeyNonce;

    public function getEncryptionKeyNonce(): ?string
    {
        return $this->encryptionKeyNonce;
    }

    public function setEncryptionKeyNonce(string $encryptionKeyNonce): self
    {
        $this->encryptionKeyNonce = $encryptionKeyNonce;

        return $this;
    }

    #[ORM\Column(type: "string", length: 255)]
    private string $passwordNonce;

    public function getPasswordNonce(): ?string
    {
        return $this->passwordNonce;
    }

    public function setPasswordNonce(string $passwordNonce): self
    {
        $this->passwordNonce = $passwordNonce;

        return $this;
    }

    #[ORM\Column(type: "string", length: 255)]
    private string $encryptedEncryptionKey;

    #[ORM\OneToMany(targetEntity: Paste::class, mappedBy: "user", orphanRemoval: true)]

    private $pastes;

    public function __construct()
    {
        $this->pastes = new ArrayCollection();
    }

    public function getEncryptedEncryptionKey(): ?string
    {
        return $this->encryptedEncryptionKey;
    }

    public function setEncryptedEncryptionKey(string $encryptedEncryptionKey): self
    {
        $this->encryptedEncryptionKey = $encryptedEncryptionKey;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Paste>
     */
    public function getPastes(): Collection
    {
        return $this->pastes;
    }

    public function addPaste(Paste $paste): self
    {
        if (!$this->pastes->contains($paste)) {
            $this->pastes[] = $paste;
            $paste->setUser($this);
        }

        return $this;
    }

    public function removePaste(Paste $paste): self
    {
        if ($this->pastes->removeElement($paste)) {
            // set the owning side to null (unless already changed)
            if ($paste->getUser() === $this) {
                $paste->setUser(null);
            }
        }

        return $this;
    }
}
