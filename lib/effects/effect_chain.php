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
        $tempFiles = []; // Für das Tracking aller temporären Dateien zum späteren Aufräumen
        
        try {
            // Aktuellen Typ für Loop-Vermeidung identifizieren
            // Wir verwenden die URL-Parameter, wenn keine andere Methode verfügbar ist
            $currentType = rex_get('rex_media_type', 'string', '');
            
            foreach ($chainTypes as $typeName) {
                $typeName = trim($typeName);
                
                // Überprüfe ob der Typ existiert
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT id FROM ' . rex::getTablePrefix() . 'media_manager_type WHERE name = ?', [$typeName]);
                
                if ($sql->getRows() === 0) {
                    $errorMsg = rex_i18n::msg('media_chain_error_type_not_found', $typeName);
                    // In die REDAXO-Systemlogs schreiben
                    rex_logger::factory()->log('error', 'Media Chain: ' . $errorMsg);
                    continue;
                }
                
                // Verhindere Endlos-Loops
                if ($typeName == $currentType) {
                    $errorMsg = rex_i18n::msg('media_chain_error_type_self_reference', $typeName);
                    // In die REDAXO-Systemlogs schreiben
                    rex_logger::factory()->log('warning', 'Media Chain: ' . $errorMsg);
                    continue;
                }
                
                // Temporäre Datei für Zwischenschritte
                if ($tempPath === null) {
                    // Erstelle temporäres Verzeichnis falls noch nicht vorhanden
                    $tempDir = rex_path::addonCache('media_chain', 'temp');
                    if (!is_dir($tempDir)) {
                        rex_dir::create($tempDir);
                    }
                    
                    $tempPath = $tempDir . '/' . uniqid('chain_') . '.' . $media->getFormat();
                    $tempFiles[] = $tempPath; // Zur Cleanup-Liste hinzufügen
                    
                    // Speichere aktuellen Zustand als Datei
                    $imageData = $media->getImage();
                    if ($imageData) {
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
                    } else {
                        // Wenn kein Bild-Objekt vorhanden, original kopieren
                        copy($originalPath, $tempPath);
                    }
                }
                
                // Kopiere die temporäre Datei in den Media-Ordner, damit der Media Manager sie findet
                $tempFileName = basename($tempPath);
                $mediaFile = rex_path::media($tempFileName);
                copy($tempPath, $mediaFile);
                $tempFiles[] = $mediaFile; // Zur Cleanup-Liste hinzufügen
                
                // Wende den Typ auf die Datei an
                try {
                    $chainedMedia = rex_media_manager::create($typeName, $tempFileName);
                    
                    // Speichere das Ergebnis als neue Zwischendatei
                    $tempPathNew = $tempDir . '/' . uniqid('chain_') . '.' . $chainedMedia->getMedia()->getFormat();
                    $tempFiles[] = $tempPathNew; // Zur Cleanup-Liste hinzufügen
                    
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
                    
                    // Alte temporäre Datei löschen
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                        // Aus der Liste der aufzuräumenden Dateien entfernen
                        $index = array_search($tempPath, $tempFiles);
                        if ($index !== false) {
                            unset($tempFiles[$index]);
                        }
                    }
                    
                    $tempPath = $tempPathNew;
                    
                } catch (rex_media_manager_not_found_exception $e) {
                    $errorMsg = rex_i18n::msg('media_chain_error_processing', $typeName, $e->getMessage());
                    // In die REDAXO-Systemlogs schreiben
                    rex_logger::factory()->log('error', 'Media Chain: ' . $errorMsg);
                    continue; // Mit dem nächsten Typ fortfahren
                }
            }
            
            // Abschließend das Ergebnis in das aktuelle Media-Objekt laden
            if ($tempPath !== null && file_exists($tempPath)) {
                // Format bestimmen
                $format = pathinfo($tempPath, PATHINFO_EXTENSION);
                
                // Bild laden
                $finalImage = null;
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
                }
                
                if ($finalImage) {
                    $media->setImage($finalImage);
                    $media->setFormat($format);
                    $media->refreshImageDimensions();
                }
            }
            
        } catch (Exception $e) {
            $errorMsg = rex_i18n::msg('media_chain_error_processing', 'general', $e->getMessage());
            // In die REDAXO-Systemlogs schreiben
            rex_logger::factory()->log('error', 'Media Chain: ' . $errorMsg);
            // Exception in die Error-Logs schreiben
            rex_logger::logException($e);
        } finally {
            // Alle temporären Dateien aufräumen
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
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
                'type' => 'text',
                'notice' => rex_i18n::msg('media_manager_effect_chain_types_notice')
            ]
        ];
    }
}
