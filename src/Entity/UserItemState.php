<?php
/*
 * Kimengumi Library
 *
 * Licensed under the EUPL, Version 1.2 or – as soon they will be approved by
 * the European Commission - subsequent versions of the EUPL (the "Licence");
 * You may not use this work except in compliance with the Licence.
 * You may obtain a copy of the Licence at:
 *
 * https://joinup.ec.europa.eu/software/page/eupl
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the Licence is distributed on an "AS IS" basis,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the Licence for the specific language governing permissions and
 * limitations under the Licence.
 *
 * @author Antonio Rossetti <antonio@rossetti.fr>
 * @copyright since 2023 Antonio Rossetti
 * @license <https://joinup.ec.europa.eu/software/page/eupl> EUPL
 */

namespace App\Entity;

use App\Repository\UserItemStateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity( repositoryClass: UserItemStateRepository::class )]
class UserItemState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column( length: 255 )]
    private ?string $name = null;

    #[ORM\OneToMany( mappedBy: 'state', targetEntity: UserItem::class )]
    private Collection $userItems;

    public function __construct()
    {
        $this->userItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName( string $name ): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, UserItem>
     */
    public function getUserItems(): Collection
    {
        return $this->userItems;
    }

    public function addUserItem( UserItem $userItem ): static
    {
        if ( !$this->userItems->contains( $userItem ) ) {
            $this->userItems->add( $userItem );
            $userItem->setState( $this );
        }

        return $this;
    }

    public function removeUserItem( UserItem $userItem ): static
    {
        if ( $this->userItems->removeElement( $userItem ) ) {
            // set the owning side to null (unless already changed)
            if ( $userItem->getState() === $this ) {
                $userItem->setState( null );
            }
        }

        return $this;
    }
}
