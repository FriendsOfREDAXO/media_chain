<?php

/**
 * Media Chain Addon.
 *
 * @author REDAXO-Team
 * @package redaxo\media-chain
 */

class rex_effect_chain extends rex_effect_abstract
{
    public function execute()
    {
        // Holen der zu verkettenden Typen aus den Parametern
        $chainTypes = explode(',', trim($this->params['types']));
        
        if (empty($chainTypes)) {
            return;
        }
        
        $media = $this->media;
        $originalPath = $media->getMediaPath();
        $tempPath = null;
        
        try {
            foreach ($chainTypes as $typeName) {
                $typeName = trim($typeName);
                
                // Verhindere Endlos-Loops
                if ($typeName == rex_media_manager::getMediaType()) {
                    continue;
                }
                
                // Temporäre Datei für Zwischenschritte
                if ($tempPath === null) {
                    $tempPath = rex_path::addonCache('media_manager', 'chain_' . uniqid() . '.' . $media->getFormat());
                    
                    // Prüfen, ob bereits ein Bild-Objekt verarbeitet wird
                    $hasImage = false;
                    
                    try {
                        // Versuche das Bild als Bild zu verarbeiten
                        $media->asImage();
                        $hasImage = true;
                        
                        // Speichere aktuellen Zustand als Datei
                        $imageData = $media->getImage();
                        $format = $media->getFormat();
                        
                        if ($format == 'jpg' || $format == 'jpeg') {
                            imagejpeg($imageData, $tempPath, 100);
                        } elseif ($format == 'png') {
                            imagepng($imageData, $tempPath, 0);
                        } elseif ($format == 'gif') {
                            imagegif($imageData, $tempPath);
                        } elseif ($format == 'webp' && function_exists('imagewebp')) {
                            imagewebp($imageData, $tempPath, 100);
                        } elseif ($format == 'avif' && function_exists('imageavif')) {
                            imageavif($imageData, $tempPath, 100);
                        } else {
                            // Fallback
                            imagepng($imageData, $tempPath, 0);
                        }
                    } catch (Exception $e) {
                        $hasImage = false;
                    }
                    
                    // Wenn kein Bild-Objekt vorhanden, original kopieren
                    if (!$hasImage) {
                        if ($originalPath && file_exists($originalPath)) {
                            copy($originalPath, $tempPath);
                        } else {
                            // Wenn auch das nicht klappt, können wir nichts tun
                            return;
                        }
                    }
                }
                
                // Wende den Typ auf die Zwischendatei an
                $chainedMedia = rex_media_manager::create($typeName, basename($tempPath));
                
                // Speichere das Ergebnis als neue Zwischendatei
                $tempPathNew = rex_path::addonCache('media_manager', 'chain_' . uniqid() . '.' . $chainedMedia->getMedia()->getFormat());
                
                $hasImage = false;
                
                try {
                    // Versuche das Ergebnis als Bild zu verarbeiten
                    $chainedMedia->getMedia()->asImage();
                    $hasImage = true;
                    $imageData = $chainedMedia->getMedia()->getImage();
                    $format = $chainedMedia->getMedia()->getFormat();
                    
                    if ($format == 'jpg' || $format == 'jpeg') {
                        imagejpeg($imageData, $tempPathNew, 100);
                    } elseif ($format == 'png') {
                        imagepng($imageData, $tempPathNew, 0);
                    } elseif ($format == 'gif') {
                        imagegif($imageData, $tempPathNew);
                    } elseif ($format == 'webp' && function_exists('imagewebp')) {
                        imagewebp($imageData, $tempPathNew, 100);
                    } elseif ($format == 'avif' && function_exists('imageavif')) {
                        imageavif($imageData, $tempPathNew, 100);
                    } else {
                        // Fallback
                        imagepng($imageData, $tempPathNew, 0);
                    }
                } catch (Exception $e) {
                    $hasImage = false;
                }
                
                // Wenn kein Bild, versuchen die Datei direkt zu kopieren
                if (!$hasImage) {
                    $sourcePath = $chainedMedia->getMedia()->getSourcePath();
                    if ($sourcePath && file_exists($sourcePath)) {
                        copy($sourcePath, $tempPathNew);
                    } else {
                        // Wenn auch das nicht klappt, behalte die vorherige Datei bei
                        $tempPathNew = $tempPath;
                        $tempPath = null; // Nicht löschen
                        continue;
                    }
                }
                
                // Alte temporäre Datei löschen
                if ($tempPath && file_exists($tempPath)) {
                    unlink($tempPath);
                }
                
                $tempPath = $tempPathNew;
            }
            
            // Abschließend das Ergebnis in das aktuelle Media-Objekt laden
            if ($tempPath && file_exists($tempPath)) {
                // Format bestimmen
                $format = pathinfo($tempPath, PATHINFO_EXTENSION);
                
                // Setze den Quellpfad auf die temporäre Datei
                $media->setSourcePath($tempPath);
                
                try {
                    // Verarbeite als Bild
                    $media->asImage();
                    
                    // Setze das Format
                    $media->setFormat($format);
                    
                    // Dimensionen aktualisieren
                    $media->refreshImageDimensions();
                } catch (Exception $e) {
                    // Falls die Bildverarbeitung fehlschlägt, behalten wir die temporäre Datei
                    // und setzen sie als Quelle, ohne Bildverarbeitung
                    $media->setFormat($format);
                }
                
                // Lösche die temporäre Datei nicht, damit sie vom Media Manager 
                // zur Ausgabe verwendet werden kann
            }
        } catch (Exception $e) {
            // Bei Fehler temporäre Dateien aufräumen
            if ($tempPath && file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            throw $e;
        }
    }
    
    public function getName()
    {
        return rex_i18n::msg('media_manager_effect_chain');
    }
    
    public function getParams()
    {
        return [
            [
                'label' => rex_i18n::msg('media_manager_effect_chain_types'),
                'name' => 'types',
                'type' => 'string',
                'notice' => rex_i18n::msg('media_manager_effect_chain_types_notice')
            ]
        ];
    }
}
