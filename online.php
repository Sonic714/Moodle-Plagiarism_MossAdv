<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
//                   Moss Anti-Plagiarism for Moodle                     //
//         github available          //
//                                                                       //
// Copyright (C) 2009 onwards  Sun Zhigang  http://sunner.cn             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

//defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require 'phpQuery.php';
require 'QueryList.php';

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/plagiarism/moss/locallib.php');

use QL\QueryList;

$cmid = optional_param('id', 0, PARAM_INT);  // Course Module ID
$userid  = optional_param('user', 0, PARAM_INT);   // User ID

if ($cmid) {
    if (! $cm = get_coursemodule_from_id('', $cmid)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf', 'assignment');
    }
}
require_login($course, true, $cm);

$context = get_system_context();
$fs = get_file_storage();
$files = $fs->get_directory_files($context->id, 'plagiarism_moss', 'files', $cmid, "/$userid/", true, true);

echo '<html> <head><style type=\"text/css\">em {font-style:normal; color:red }</style></head> <body>';
    
foreach ($files as $file){
    
    echo '<p>filename = '.$file->get_filename().'</p>';
    $content = get_clear_utf8_content($file);

    $SentenceList1 = explode('.',$content);
    $SentenceList2 = explode('¡£',$content);
    $ct1 = count($SentenceList1);
    $ct2 = count($SentenceList2);
    $SentenceList = $SentenceList2;
    $ct = $ct2;
    if($ct1>$ct2){
        $SentenceList = $SentenceList1;
        $ct = $ct1;
    }
    shuffle($SentenceList);
    $maxn = ceil($ct / 2);
    $res = array();
    $flag = 0;

    for ($i=0; $i<=$maxn; $i++) {
        $SentenceList[$i] = str_replace(' ','',$SentenceList[$i]);
        //$tempstr = str_replace(' ','',$SentenceList[$i]);
        $con = strlen($SentenceList[$i]);
        if ($con==0){
            continue;
        }
        $res[$i] = array();
        $res[$i]['sim'] = 0;
        $url ='http://www.baidu.com/s?pn=0&wd='.$SentenceList[$i];
        $data = QueryList::Query($url,array(
            'totaltext' => array('.nums_text','text')
        ),'#wrapper>#wrapper_wrapper>#container>.head_nums_cont_outer>.head_nums_cont_inner>.nums')->data;
        $total = intval(findNum($data[0]['totaltext']));
        $len = 10;
        if($total<10){
            $len = $total;
        }
        for ($j=0; $j<$len; $j++) {
            $data = QueryList::Query($url,array(
                'titlered' => array('em','text')
            ),'#wrapper>#wrapper_wrapper>#container>#content_left>#'.($j+1).'>h3>a')->data;
            $dct1 = count($data);
            $tit = 0;
            for ($k=0; $k<$dct1; $k++) {
                $tit += strlen(str_replace(' ','',$data[$k]['titlered']));
            }
            $data2 = QueryList::Query($url,array(
                'summaryred' => array('em','text')
            ),'#wrapper>#wrapper_wrapper>#container>#content_left>#'.($j+1).'>.c-abstract')->data;
            $dct2 = count($data2);
            $sum = 0;
            for ($k=0; $k<$dct2; $k++) {
                $sum += strlen(str_replace(' ','',$data2[$k]['summaryred']));
            }
            $simt = $tit/$con;
            $sims = $sum/$con;
            $tempsim = $simt;
            if($simt<$sims){
                $tempsim = $sims;
            }
            if($tempsim>1){
                $tempsim = 1;
            }
            if($tempsim>$res[$i]['sim']){
                $res[$i]['sim'] = $tempsim;
                $res[$i]['data1'] = QueryList::Query($url,array(
                    'title' => array('a','text')
                ),'#wrapper>#wrapper_wrapper>#container>#content_left>#'.($j+1).'>h3')->data;
                $res[$i]['data2'] = QueryList::Query($url,array(
                    'summary' => array('.c-abstract','text')
                ),'#wrapper>#wrapper_wrapper>#container>#content_left>#'.($j+1))->data;
            }
        }
        if($res[$i]['sim']>0.4){
            echo '<p>local sentence = '.$SentenceList[$i].'</p>';
            echo '<p>net title = '.$res[$i]['data1'][0]['title'];
            echo '</p><p>net summary = '.$res[$i]['data2'][0]['summary'];
            echo '</p><p>similarity = '.(round($res[$i]['sim']*100)/100.0).'</p>';
            $flag++;
        }
    }
    if($flag==0){
        echo '<p>No plagiarism detected.</p>';
    }
    echo '<p></p>';
}
echo '</body></html>';

function get_clear_utf8_content($file) {
    $localewincharset = get_string('localewincharset', 'langconfig');
    
    $filen = $file->get_filename();
    $file_type = strtolower(substr($filen, strlen($filen)-4, 4));
    
    global $CFG;
    $tempdir = $CFG->dataroot.'/temp/moss/';
    
    if ($CFG->ostype == 'WINDOWS') {
        // the tempdir will be passed to cygwin which require '/' path spliter
        $tempdir = str_replace('\\', '/', $tempdir);
    }
    if (array_search($file_type, array('.pdf', '.rtf', '.odt', '.doc', 'docx'))) {
        $temp_file = $tempdir."/$filen.tmp";
        $file->copy_content_to($temp_file);
        switch ($file_type) {
            case '.pdf':
                $content = pdf2text($temp_file);
                break;
            case '.rtf':
                $content = core_text::entities_to_utf8(rtf2text($temp_file));
                break;
            case '.odt':
                $content =  getTextFromZippedXML($temp_file,'content.xml');
                break;
            case '.doc':
                $antiwordpath = get_config('plagiarism_moss', 'antiwordpath');
                $magic = file_get_contents($temp_file, NULL, NULL, -1, 2);
                if ($magic === 'PK') {
                    // It is really a docx
                    $content = getTextFromZippedXML($temp_file,'word/document.xml');
                } else if (empty($antiwordpath) || !is_executable($antiwordpath)) {
                    $content = core_text::entities_to_utf8(doc2text($temp_file));
                } else {
                    $content = shell_exec($antiwordpath.' -f -w 0 '.escapeshellarg($temp_file));
                    if (empty($content)) { // antiword can not recognize this file
                        $content = core_text::entities_to_utf8(doc2text($temp_file));
                    }
                }
                break;
            case 'docx':
                $content = getTextFromZippedXML($temp_file,'word/document.xml');
                break;
        }
        unlink($temp_file);
        return wordwrap2($content, 80);
    }
    
    // Files no need to covert format go here
    $content = $file->get_content();
    
    if (!mb_check_encoding($content, 'UTF-8')) {
        if (mb_check_encoding($content, $localewincharset)) {
            // Convert content charset to UTF-8
            $content = core_text::convert($content, $localewincharset);
        } else {
            // Unknown charset, possible binary file. Skip it
            mtrace("\tSkip unknown charset/binary file ".$file->get_filepath().$file->get_filename());
            return false;
        }
    }
    
    return $content;
}
function wordwrap2($text, $width) {
    $i = 0;
    $return = '';
    $linelen = 0;
    $prev_ch = '';
    while (($ch = mb_substr($text, $i, 1, 'UTF-8')) !== '') {
        if ($linelen >= $width and (!ctype_alnum($prev_ch) or ctype_space($ch))) { // Do not break latin words
            $return .= "\n";
            $linelen = 0;
        }
        if ($linelen != 0 or !ctype_space($ch)) {   // trim heading whitespaces
            $return .= $ch;
            $linelen += mb_strwidth($ch, 'UTF-8');   // Multy-byte chars may twice the width
        }
        if ($ch === "\n") {
            $linelen = 0;
        }
        $i++;
        $prev_ch = $ch;
    }
    return $return;
}

function findNum($str=''){
    $str=trim($str);
    if(empty($str)){return '';}
    $result='';
    for($i=0;$i<strlen($str);$i++){
        if(is_numeric($str[$i])){
            $result.=$str[$i];
        }
    }
    return $result;
}