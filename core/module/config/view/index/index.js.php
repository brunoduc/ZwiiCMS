/**
 * This file is part of Zwii.
 *
 * For full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 *
 * @author Frédéric Tempez <frederic.tempez@outlook.com>
 * @copyright Copyright (C) 2018-2020, Frédéric Tempez
 * @license GNU General Public License, version 3
 * @link http://zwiicms.com/
 */

/**
 * Modification de l'affichage de l'icône de langues
 */


var configdisablei18nDOM = $("#configdisablei18n");
configdisablei18nDOM.on("change", function() {
    if ($("input[name=configdisablei18n]").is(':checked')) {
        $(".zwiico-flag").css("display","none");
        $(".zwiico-flag").parents("li").css("display","none");
    } else {
        $(".zwiico-flag").css('display','block');
        $(".zwiico-flag").parents("li").css("display","inline");
    }
});
