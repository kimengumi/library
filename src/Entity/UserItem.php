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

use App\Repository\UserItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity( repositoryClass: UserItemRepository::class )]
#[ORM\UniqueConstraint( name: 'user_item_idx', columns: [ 'user_id', 'item_id' ] )]
class UserItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne( inversedBy: 'userItems' )]
    #[ORM\JoinColumn( nullable: false )]
    private ?User $user = null;

    #[ORM\ManyToOne( inversedBy: 'userItems' )]
    #[ORM\JoinColumn( nullable: false )]
    private ?Item $item = null;

    #[ORM\ManyToOne( inversedBy: 'userItems' )]
    private ?UserItemState $state = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser( ?User $user ): static
    {
        $this->user = $user;

        return $this;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem( ?Item $item ): static
    {
        $this->item = $item;

        return $this;
    }

    public function getState(): ?UserItemState
    {
        return $this->state;
    }

    public function setState( ?UserItemState $state ): static
    {
        $this->state = $state;

        return $this;
    }
}
