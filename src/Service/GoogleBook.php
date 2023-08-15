<?php
/*
*
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

namespace App\Service;

class GoogleBook
{
    public static function findOne( string $search ): array|false
    {
        //Search on google book
        $json = json_decode( file_get_contents( getenv( 'GOOGLE_BOOK_SEARCH_ENDPOINT' ) . htmlentities( $search ) ) );

        if ( json_last_error() ) {
            Tools::devDump( [ 'Google Book json error' => json_last_error_msg() ] );
            return false;
        }
        if ( !isset( $json->items[0] ) ) {
            Tools::devDump( [ 'Google Book API ret' => $json ] );
            return false;
        }

        return $json->items[0];
    }
}
