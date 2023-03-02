<?php

namespace App\Entity;

use App\Repository\PasteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasteRepository::class)]
class Paste
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private $url;

    #[ORM\Column(type: "text")]
    private $content;

    #[ORM\Column(type: "dateinterval", options: ["default" => "2 weeks"])]
    private \DateInterval $TTL;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ["default" => "now"])]
    private \DateTimeInterface $created;

    public function __construct()
    {
        $this->setCreated(new \DateTime());
        $this->attachments = new ArrayCollection();
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "pastes")]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\Column(type: "string", length: 20)]
    private $nonce;

    #[ORM\Column(type: "boolean")]
    private $public;


    #[ORM\OneToMany(mappedBy: 'paste', targetEntity: Attachment::class, orphanRemoval: true)]
    private $attachments;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getTTL(): \DateInterval
    {
        return $this->TTL;
    }

    public function setTTL(\DateInterval $TTL): self
    {
        $this->TTL = $TTL;

        return $this;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function setNonce(string $nonce): self
    {
        $this->nonce = $nonce;

        return $this;
    }

    public function getPublic(): ?bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }


    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
            $attachment->setPaste($this);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachments->removeElement($attachment)) {
            // set the owning side to null (unless already changed)
            if ($attachment->getPaste() === $this) {
                $attachment->setPaste(null);
            }
        }

        return $this;
    }

    public function then(): \DateTimeInterface
    {
        return $this->getCreated()->add($this->getTTL());
    }
}
