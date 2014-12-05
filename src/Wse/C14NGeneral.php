<?php

namespace Wse;

/**
 * xmlseclibs.php
 *
 * Copyright (c) 2007-2010, Robert Richards <rrichards@cdatazone.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Robert Richards nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author     Robert Richards <rrichards@cdatazone.org>
 * @copyright  2007-2010 Robert Richards <rrichards@cdatazone.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    1.3.0-dev
 */

/**
 * Functions to generate simple cases of Exclusive Canonical XML - Callable function is C14NGeneral()
 * i.e.: $canonical = C14NGeneral($domelement, TRUE);
 */

class C14NGeneral
{
    /* helper function */
    public function sortAndAddAttrs($element, $arAtts) {
       $newAtts = array();
       foreach ($arAtts AS $attnode) {
          $newAtts[$attnode->nodeName] = $attnode;
       }
       ksort($newAtts);
       foreach ($newAtts as $attnode) {
          $element->setAttribute($attnode->nodeName, $attnode->nodeValue);
       }
    }
    
    /* helper function */
    public function canonical($tree, $element, $withcomments) {
        if ($tree->nodeType != XML_DOCUMENT_NODE) {
            $dom = $tree->ownerDocument;
        } else {
            $dom = $tree;
        }
        if ($element->nodeType != XML_ELEMENT_NODE) {
            if ($element->nodeType == XML_DOCUMENT_NODE) {
                foreach ($element->childNodes AS $node) {
                    $this->canonical($dom, $node, $withcomments);
                }
                return;
            }
            if ($element->nodeType == XML_COMMENT_NODE && ! $withcomments) {
                return;
            }
            $tree->appendChild($dom->importNode($element, TRUE));
            return;
        }
        $arNS = array();
        if ($element->namespaceURI != "") {
            if ($element->prefix == "") {
                $elCopy = $dom->createElementNS($element->namespaceURI, $element->nodeName);
            } else {
                $prefix = $tree->lookupPrefix($element->namespaceURI);
                if ($prefix == $element->prefix) {
                    $elCopy = $dom->createElementNS($element->namespaceURI, $element->nodeName);
                } else {
                    $elCopy = $dom->createElement($element->nodeName);
                    $arNS[$element->namespaceURI] = $element->prefix;
                }
            }
        } else {
            $elCopy = $dom->createElement($element->nodeName);
        }
        $tree->appendChild($elCopy);
    
        /* Create DOMXPath based on original document */
        $xPath = new DOMXPath($element->ownerDocument);
    
        /* Get namespaced attributes */
        $arAtts = $xPath->query('attribute::*[namespace-uri(.) != ""]', $element);
    
        /* Create an array with namespace URIs as keys, and sort them */
        foreach ($arAtts AS $attnode) {
            if (array_key_exists($attnode->namespaceURI, $arNS) &&
                ($arNS[$attnode->namespaceURI] == $attnode->prefix)) {
                continue;
            }
            $prefix = $tree->lookupPrefix($attnode->namespaceURI);
            if ($prefix != $attnode->prefix) {
               $arNS[$attnode->namespaceURI] = $attnode->prefix;
            } else {
                $arNS[$attnode->namespaceURI] = NULL;
            }
        }
        if (count($arNS) > 0) {
            asort($arNS);
        }
    
        /* Add namespace nodes */
        foreach ($arNS AS $namespaceURI=>$prefix) {
            if ($prefix != NULL) {
                  $elCopy->setAttributeNS("http://www.w3.org/2000/xmlns/",
                                   "xmlns:".$prefix, $namespaceURI);
            }
        }
        if (count($arNS) > 0) {
            ksort($arNS);
        }
    
        /* Get attributes not in a namespace, and then sort and add them */
        $arAtts = $xPath->query('attribute::*[namespace-uri(.) = ""]', $element);
        $this->sortAndAddAttrs($elCopy, $arAtts);
    
        /* Loop through the URIs, and then sort and add attributes within that namespace */
        foreach ($arNS as $nsURI=>$prefix) {
           $arAtts = $xPath->query('attribute::*[namespace-uri(.) = "'.$nsURI.'"]', $element);
           $this->sortAndAddAttrs($elCopy, $arAtts);
        }
    
        foreach ($element->childNodes AS $node) {
            $this->canonical($elCopy, $node, $withcomments);
        }
    }
    
    /**
     * @param $element - DOMElement for which to produce the canonical version of
     * @param $exclusive - boolean to indicate exclusive canonicalization (must pass TRUE)
     * @param $withcomments - boolean indicating wether or not to include comments in canonicalized form
     */
    public function C14NGeneral($element, $exclusive=FALSE, $withcomments=FALSE) {
        /* IF PHP 5.2+ then use built in canonical functionality */
        $php_version = explode('.', PHP_VERSION);
        if (($php_version[0] > 5) || ($php_version[0] == 5 && $php_version[1] >= 2) ) {
            return $element->C14N($exclusive, $withcomments);
        }
    
        /* Must be element or document */
        if (! $element instanceof DOMElement && ! $element instanceof DOMDocument) {
            return NULL;
        }
        /* Currently only exclusive XML is supported */
        if ($exclusive == FALSE) {
            throw new Exception("Only exclusive canonicalization is supported in this version of PHP");
        }
    
        $copyDoc = new DOMDocument();
        $this->canonical($copyDoc, $element, $withcomments);
        return $copyDoc->saveXML($copyDoc->documentElement, LIBXML_NOEMPTYTAG);
    }
}
