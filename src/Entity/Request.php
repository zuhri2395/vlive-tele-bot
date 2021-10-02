<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="request", indexes={@ORM\Index(name="vlive_idx", columns={"link"})})
 */
class Request
{

    /**
     * @var int
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    private $id;

    /**
     * @var int
     * @ORM\Column(name="user_id", type="integer")
     */
    private $user_id;

    /**
     * @var string
     * @ORM\Column(name="link", type="string", length=255)
     */
    private $link;

    /**
     * @var string
     * @ORM\Column(name="quality", type="string", length=255)
     */
    private $quality;

    /**
     * @var string
     * @ORM\Column(name="subs", type="string", length=255, nullable=true)
     */
    private $subs;

    /**
     * @var string
     * @ORM\Column(name="file_id", type="string", length=255, nullable=true)
     */
    private $file_id;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * @param int $user_id
     */
    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * @param string $link
     */
    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    /**
     * @return string
     */
    public function getQuality(): string
    {
        return $this->quality;
    }

    /**
     * @param string $quality
     */
    public function setQuality(string $quality): void
    {
        $this->quality = $quality;
    }

    /**
     * @return string
     */
    public function getSubs(): string
    {
        return $this->subs;
    }

    /**
     * @param string $subs
     */
    public function setSubs(string $subs): void
    {
        $this->subs = $subs;
    }

    /**
     * @return string
     */
    public function getFileId(): string
    {
        return $this->file_id;
    }

    /**
     * @param string $file_id
     */
    public function setFileId(string $file_id): void
    {
        $this->file_id = $file_id;
    }

}