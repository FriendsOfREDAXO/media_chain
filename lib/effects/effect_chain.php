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
        
        // Aktueller Medientyp zur Vermeidung von Endlosschleifen
        $currentType = rex_get('rex_media_type', 'string', '');
        
        $media = $this->media;
        $originalPath = $media->getMediaPath();
        $tempPath = null;
        
        try {
            foreach ($chainTypes as $typeName) {
                $typeName = trim($typeName);
                
                // Verhindere Endlos-Loops
                if ($typeName == $currentType) {
                    continue;
                }
                
                // Debug-Ausgabe für die Entwicklung
                // file_put_contents(rex_path::addonCache('media_chain', 'debug.log'), 'Applying type: ' . $typeName . PHP_EOL, FILE_APPEND);
                
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
                // Kopiere die temporäre Datei in den media/-Ordner, damit sie gefunden wird
                $tmpMediaName = 'chain_' . uniqid() . '.' . pathinfo($tempPath, PATHINFO_EXTENSION);
                $mediaPath = rex_path::media($tmpMediaName);
                copy($tempPath, $mediaPath);
                
                try {
                    // Anwenden des Media-Manager-Typs
                    $chainedMedia = rex_media_manager::create($typeName, $tmpMediaName);
                    
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
                
                // Löschen der temporären Mediendatei
                if (file_exists($mediaPath)) {
                    unlink($mediaPath);
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
                
                try {
                    // Bild laden
                    if ($format == 'jpg' || $format == 'jpeg') {
                        $finalImage = imagecreatefromjpeg($tempPath);
                    } elseif ($format == 'png') {
                        $finalImage = imagecreatefrompng($tempPath);
                        imagealphablending($finalImage, false);
                        imagesavealpha($finalImage, true);
                    } elseif ($format == 'gif') {
                        $finalImage = imagecreatefromgif($tempPath);
                    } elseif ($format == 'webp' && function_exists('imagecreatefromwebp')) {
                        $finalImage = imagecreatefromwebp($tempPath);
                    } elseif ($format == 'avif' && function_exists('imagecreatefromavif')) {
                        $finalImage = imagecreatefromavif($tempPath);
                    } else {
                        throw new Exception('Unsupported image format');
                    }
                    
                    // Setze das Bild direkt
                    $media->setImage($finalImage);
                    $media->setFormat($format);
                    $media->refreshImageDimensions();
                    
                    // Temporäre Datei löschen, da wir das Bild direkt im Speicher haben
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                } catch (Exception $e) {
                    // Falls die Bildverarbeitung fehlschlägt, behalten wir die temporäre Datei
                    // und setzen sie als Quelle, ohne Bildverarbeitung
                    $media->setSourcePath($tempPath);
                    $media->setFormat($format);
                    
                    // Löschen wir die temporäre Datei nicht, damit sie 
                    // für die Ausgabe verwendet werden kann
                }
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
