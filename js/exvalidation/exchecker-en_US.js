/*!
 * exValidation
 *
 * @version   : 1.3.1
 * @author    : nori (norimania@gmail.com)
 * @copyright : 5509 (http://5509.me/)
 * @license   : The MIT License
 * @link      : http://5509.me/log/exvalidation
 * @modified  : 2012-03-17 16:14
 */
;(function($) {
  // Extend validation rules
  $.exValidationRules = $.extend($.exValidationRules, {
    chkrequired: [
      "This feild is required",
      function(txt, t) {
        if ( $(t).hasClass("chkgroup") ) {
          var flag = 0;
          $("input,select",t).each(function() {
            if ( $(this).val().length > 0 ) flag++;
          });
          if ( txt && flag === $("input,select", t).length ) {
            if ( /^[ 　\r\n\t]+$/.test(txt) ) {
              return false;
            } else {
              return true;
            }
          }
        } else {
          if ( txt && txt.length>0 ) {
            if ( /^[ 　\r\n\t]+$/.test(txt) ) {
              return false;
            } else {
              return true;
            }
          }
        }
      }
    ],
    chkselect: [
      "This feild is required",
      function(txt, t) {
        if ( txt && txt.length>0 ) {
          if ( /^[ 　\r\n\t]+$/.test(txt) ) {
            return false;
          } else {
            return true;
          }
        }
      }
    ],
    chkretype: [
      "You has wrong words",
      function(txt, t) {
        var elm = $("#" + $(t).attr("class").split("retype\-")[1].split(/\b/)[0]);
        if ( elm.hasClass("chkgroup") ) {
          var chktxt = $("input", elm), txt = $("input", t);
          for ( var i = 0, flag = false; i < chktxt.length; i++ ) {
            if ( chktxt[i].value === txt[i].value ) {
              flag = true;
            } else {
              flag = false;
              break;
            }
          }
          if ( flag ) return true;
        } else {
          return elm.val() == txt;
        }
      }
    ],
    chkemail: [
      "Not valid Email address",
      /^(?:[^\@]+?@[A-Za-z0-9_\.\-]+\.+[A-Za-z\.\-\_]+)*$/
    ],
    chkhankaku: [
      "Multibytes characters are not allowed",
      /^(?:[a-zA-Z0-9@\<\>\;\:\[\]\{\}\|\^\=\/\!\*\`\"\#\$\+\%\&\'\(\)\,\.\-\_\?\\\s]*)*$/
    ], //"
    chkzenkaku: [
      "Using only multibytes characters",
      /^(?:[^a-zA-Z0-9@\<\>\;\:\[\]\{\}\|\^\=\/\!\*\"\#\$\+\%\&\'\(\)\,\.\-\_\?\\\s]+)*$/
    ],
    chkhiragana: [
      "Using only HIRAGANA",
      /^(?:[ぁ-ゞ]+)*$/
    ],
    chkkatakana: [
      "Using only KATAKANA",
      /^(?:[ァ-ヾ]+)*$/
    ],
    chkfurigana: [
      "Using only HIRAGANA, multibytes numeral, 〜, ー and（）",
      /^(?:[ぁ-ゞ０-９ー～（）\(\)\d 　]+)*$/
    ],
    chknochar: [
      "Using only alphanumeric",
      /^(?:[a-zA-Z0-9]+)*$/
    ],
    chknocaps: [
      "Using only lower-case alphanumeric",
      /^(?:[a-z0-9]+)*$/
    ],
    chknumonly: [
      "Using only numeral",
      /^(?:[0-9]+)*$/
    ],
    chkmin: [
      "is minimum length",
      function(txt, t) {
        if ( txt.length === 0 ) return true;
         var length = $(t).attr("class").match(/min(\d+)/) ? RegExp.$1 : null;
        return txt.length >= length;
      }
    ],
    chkmax: [
      "is maximum length",
      function(txt, t) {
        var length = $(t).attr("class").match(/max(\d+)/) ? RegExp.$1 : null;
        return txt.length <= length;
      }
    ],
    chkradio: [
      "Please choose",
      function(txt, t) {
        return $("input:checked",t).length>0;
      }
    ],
    chkcheckbox: [
      "Please choose",
      function(txt, t) {
        return $("input:checked",t).length>0;
      }
    ],
    chkzip: [
      "Not valid zip code",
      /^(?:¥d{3}-?¥d{4}$|^¥d{3}-?¥d{2}$|^¥d{3})*$/
    ],
    chkurl: [
      "Not valid URL",
      /^(?:(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?)*$/
    ],
    chktel: [
      "Not valid telephone number",
      /^(?:\(?\d+\)?\-?\d+\-?\d+)*$/
    ],
    chkfax: [
      "Not valid fax number",
      /^(?:\(?\d+\)?\-?\d+\-?\d+)*$/
    ],
    chkfile: [
      "Please choose file",
      function(txt, t) {
        if ( txt && txt.length>0 ) {
          if ( /^[ 　\r\n\t]+$/.test(txt) ) {
            return false;
          } else {
            return true;
          }
        }
      }
    ]
  });
})(jQuery);

