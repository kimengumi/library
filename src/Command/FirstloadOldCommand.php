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

namespace App\Command;

use App\Entity\Category;
use App\Entity\Item;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'firstload:old',
    description: 'Import library database from the previous version',
)]
class FirstloadOldCommand extends Command
{
    protected ?EntityManagerInterface $em;
    protected ?Connection             $oldDbConn;
    protected ?Connection             $oldDbPagesConn;
    protected ?OutputInterface        $output;

    public function __construct( EntityManagerInterface $entityManager )
    {
        $this->em = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument( 'old-db-url', InputArgument::OPTIONAL, 'Old DB (url format)',
            'mysql://dev:dev@127.0.0.1:3306/dev_kim-www?serverVersion=10.6.12-MariaDB&charset=utf8' );
        $this->addArgument( 'old-db-pages-url', InputArgument::OPTIONAL, 'Old DB (url format)',
            'mysql://dev:dev@127.0.0.1:3306/dev_kim-www_pages?serverVersion=10.6.12-MariaDB&charset=utf8' );
    }

    protected function execute( InputInterface $input, OutputInterface $output ): int
    {
        $output->writeln( '<fg=bright-red>This is a FIRST-LOAD, existing data will be OVERWRITTEN during the process</>' );
        if ( !$this->getHelper( 'question' )->ask( $input, $output, new ConfirmationQuestion(
            'Are you sure you want to start the import (yes/no) ? ', false ) ) ) {
            return Command::SUCCESS;
        }
        $this->output = $output;

        // Old version does not have doctrine style entites & was a little across 2 databases;
        $this->oldDbConn      = DriverManager::getConnection( [
            'driver' => 'pdo_mysql',
            'url'    => $input->getArgument( 'old-db-url' ), ] );
        $this->oldDbPagesConn = DriverManager::getConnection( [
            'driver' => 'pdo_mysql',
            'url'    => $input->getArgument( 'old-db-pages-url' ), ] );


        $output->writeln( 'Import authors ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_auteurs" ) as $old ) {
            $this->newInsertOrUpdate( 'author', [ 'id' => $old['id'] ], [ 'name' => addslashes( html_entity_decode( $old['auteur'] ) ) ] );
        }

        $output->writeln( 'Import publishers ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_editeurs" ) as $old ) {
            $this->newInsertOrUpdate( 'publisher', [ 'id' => $old['id'] ], [ 'name' => addslashes( html_entity_decode( $old['editeur'] ) ) ] );
        }

        $output->writeln( 'Import categories ...' );
        $this->newSetForeignKeyChecks( false );
        foreach ( $this->oldQ( "SELECT * FROM biblio_categories" ) as $old ) {
            $this->newInsertOrUpdate( 'category', [
                'id' => $old['id'], 'lft' => 0, 'rgt' => 0, 'lvl' => 0, 'tree_root' => 1,
            ], [
                'name'               => addslashes( html_entity_decode( $old['categorie'] ) ),
                'is_series'          => ( $old['idtype'] || $old['nbean'] ) ? 1 : 0,
                'is_finished'        => ( $old['idtype'] == 1 ) ? 1 : 0,
                'is_stopped'         => ( $old['idtype'] == 3 ) ? 1 : 0,
                'series_items_count' => (int)$old['nbean'],
                'parent_id'          => (int)$old['idparent'] ? $old['idparent'] : null,
            ] );
        }
        $this->newUpdate( 'category', [ 'id' => 1 ], [ 'name' => 'Library' ] );
        $this->newSetForeignKeyChecks( true );

        $output->writeln( 'Import items ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_eans" ) as $old ) {
            $this->newInsertOrUpdate( 'item', [ 'ean' => $old['ean'] ], [
                'title'          => addslashes( html_entity_decode( $old['nom'] ) ),
                'category_id'    => (int)$old['idcategorie'] ? $old['idcategorie'] : null,
                'publisher_id'   => (int)$old['idediteur'] ? $old['idediteur'] : null,
                'page_count'     => (int)$old['nbpages'],
                'published_date' => $old['datepubl'] ?? null,
                'img_width'      => (int)$old['imghaut'],
                'img_height'     => (int)$old['imglarge'],
                'created'        => $old['created'] ?? null,
                'updated'        => $old['modified'] ?? null,
            ] );
        }

        $output->writeln( 'Caching Items ids by EAN ...' );
        $itemEans = $this->newQ( sprintf( 'SELECT item.id,item.ean FROM %s as item', Item::class ) );
        foreach ( $itemEans as $itemEan ) {
            $eanId[ $itemEan['ean'] ] = $itemEan['id'];
        }

        $output->writeln( 'Import items authors ...' );
        foreach ( $this->oldQ( "SELECT ean,idauteur FROM biblio_eans WHERE idauteur > 0" ) as $old ) {
            $this->newInsertOrUpdate( 'item_author', [ 'item_id' => $eanId[ $old['ean'] ], 'author_id' => $old['idauteur'] ], [] );
        }

        $output->writeln( 'Import items description ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_eans_descr" ) as $old ) {
            $this->newInsertOrUpdate( 'item_description', [ 'item_id' => $eanId[ $old['ean'] ] ],
                [ 'description' => addslashes( html_entity_decode( $old['descr'] ) ) ] );
        }

        $output->writeln( 'Import categories leading item...' );
        foreach ( $this->oldQ( "SELECT id,eanref FROM biblio_categories WHERE eanref" ) as $old ) {
            $duplicates = $this->newQ( sprintf(
                'SELECT c.id FROM %s AS c WHERE c.id!=%d AND c.leadingItem=%d',
                Category::class, $old['id'], $eanId[ $old['eanref'] ] ) );
            if ( !count( $duplicates ) ) {
                $this->newUpdate( 'category', [ 'id' => $old['id'] ], [ 'leading_item_id' => $eanId[ $old['eanref'] ] ] );
            }
        }

        $output->writeln( 'Import users...' );
        $oldUsersHavingLibrary = [];
        foreach ( $this->oldQ( "SELECT DISTINCT idcompte FROM biblio_theque" ) as $oldUser ) {
            $oldUsersHavingLibrary[] = $oldUser['idcompte'];
        }
        $oldUsers = $this->oldPagesQ( sprintf( 'SELECT * FROM pg_users WHERE ID IN(%s)', implode( ',', $oldUsersHavingLibrary ) ) );
        foreach ( $oldUsers as $oldUser ) {
            $this->newInsertOrUpdate( 'user', [
                'id'           => $oldUser['ID'],
                'username'     => $oldUser['user_login'],
                'email'        => $oldUser['user_email'],
                'password'     => 'ToBeChanged',
                'roles'        => "['ROLE_USER']",
                'display_name' => $oldUser['display_name'],
                'is_verified'  => (int)!(int)$oldUser['user_status'],
            ] );

        }

        $output->writeln( 'Import users items ...' );
        foreach ( $this->oldQ( "SELECT * FROM biblio_theque" ) as $old ) {
            $this->newInsertOrUpdate( 'user_item', [ 'user_id' => $old['idcompte'], 'item_id' => $eanId[ $old['ean'] ] ] );
        }

        $output->writeln( 'Calculate categories tree ...' );
        /** @var CategoryRepository $categoryRepo */
        $categoryRepo = $this->em->getRepository( Category::class );
        $categoryRepo->verify();
        $categoryRepo->recover();
        $this->em->flush();

        return Command::SUCCESS;
    }

    protected function oldQ( string $sql ): array
    {
        $this->output->writeln( $sql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->oldDbConn->prepare( $sql )->executeQuery()->fetchAllAssociative();
    }

    protected function oldPagesQ( string $sql ): array
    {
        $this->output->writeln( $sql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->oldDbPagesConn->prepare( $sql )->executeQuery()->fetchAllAssociative();
    }

    protected function newQ( string $dql ): array
    {
        $this->output->writeln( $dql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->em->createQuery( $dql )->getArrayResult();
    }

    protected function newInsertOrUpdate( string $table, array $idxData = [], array $stdData = [] )
    {
        $idxData = array_filter( $idxData, fn( $value ) => !is_null( $value ) );
        $stdData = array_filter( $stdData, fn( $value ) => !is_null( $value ) );
        $allData = array_merge( $idxData, $stdData );

        if ( !count( $allData ) ) {
            return false;
        }

        $sql = sprintf( 'INSERT %s INTO `%s` ( `%s` ) VALUES( "%s" ) ',
            !count( $stdData ) ? 'IGNORE' : '',
            $table,
            implode( '`,`', array_keys( $allData ) ),
            implode( '","', $allData )
        );
        foreach ( $stdData as $key => $val ) {
            $sql .= sprintf( '%s `%s`="%s"', $key === array_key_first( $stdData ) ? ' ON DUPLICATE KEY UPDATE' : ',', $key, $val );
        }

        $this->output->writeln( $sql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->em->createNativeQuery( $sql, new ResultSetMapping() )->execute();
    }

    protected function newUpdate( string $table, array $idxData = [], array $stdData )
    {
        $idxData = array_filter( $idxData, fn( $value ) => !is_null( $value ) );
        $stdData = array_filter( $stdData, fn( $value ) => !is_null( $value ) );

        if ( !count( $stdData ) ) {
            return false;
        }

        $sql = sprintf( 'UPDATE `%s`', $table );
        foreach ( $stdData as $key => $val ) {
            $sql .= sprintf( '%s `%s`="%s"', $key === array_key_first( $stdData ) ? ' SET' : ',', $key, $val );
        }
        foreach ( $idxData as $key => $val ) {
            $sql .= sprintf( '%s `%s`="%s"', $key === array_key_first( $idxData ) ? ' WHERE' : ' AND', $key, $val );
        }

        $this->output->writeln( $sql, OutputInterface::VERBOSITY_VERY_VERBOSE );
        return $this->em->createNativeQuery( $sql, new ResultSetMapping() )->execute();
    }

    public function newSetForeignKeyChecks( bool $enable = true )
    {
        return $this->em->createNativeQuery( sprintf( 'SET FOREIGN_KEY_CHECKS = %d;', $enable ), new ResultSetMapping() )->execute();
    }
}
