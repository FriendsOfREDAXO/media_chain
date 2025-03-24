# Media Chain AddOn für REDAXO

Das Media Chain AddOn fügt dem Media Manager einen neuen Effekt hinzu, mit dem mehrere Media Manager Typen in einer Kette hintereinander angewendet werden können.

## Features

- Verkettung beliebig vieler Media Manager Typen
- Einfache kommagetrennte Konfiguration
- Unterstützung verschiedener Bildformate (JPG, PNG, GIF, WebP, AVIF)
- Sicherer Umgang mit temporären Dateien
- Vermeidung von Endlosschleifen

## Installation

1. Im REDAXO-Backend unter "Installer" das AddOn "media_chain" installieren
2. Alternativ: ZIP-Datei herunterladen, entpacken und in den Ordner `/redaxo/src/addons/media_chain` hochladen

## Verwendung

1. Erstelle mehrere Media Manager Typen mit den gewünschten Effekten
2. Erstelle einen neuen Media Manager Typ und füge den "Verketten von Medientypen"-Effekt hinzu
3. Gib im Feld "Media-Manager-Typen" eine kommagetrennte Liste der anzuwendenden Typen an, z.B. `resize_small,header,make_greyscale`
4. Der neue Typ wendet nun alle angegebenen Typen nacheinander an

## Beispiele

### Mehrere Typen kombinieren

```
resize_small,watermark,webp_convert
```

Dies wird zuerst das Bild mit dem Typ `resize_small` verkleinern, dann mit `watermark` ein Wasserzeichen hinzufügen und schließlich mit `webp_convert` in das WebP-Format konvertieren.

### Responsive Bilder mit Art Direction

```
focus_16_by_9,resize_small
```

Dieser Effekt kann genutzt werden, um zuerst einen bestimmten Ausschnitt für mobile Geräte zu wählen und dann das Bild zu verkleinern.

### Hochqualitative Bildbearbeitung

```
image_optimize,resize_large,enhance_quality,watermark,srcset_large
```

Optimiere das Bild, passe die Größe an, verbessere die Qualität und füge ein Wasserzeichen hinzu - alles in einem Durchgang.

## Wie es funktioniert

Der Chain-Effekt erzeugt temporäre Dateien für die Zwischenschritte und führt jeden der angegebenen Media Manager Typen nacheinander aus. Die Ausgabe eines Typs dient als Eingabe für den nächsten Typ in der Kette.

## Besonderheiten

### Formatkonvertierung

Wenn einer der verketteten Typen das Bildformat ändert (z.B. von JPG zu WebP), wird diese Änderung durch die gesamte Kette beibehalten. Dies ermöglicht komplexe Transformationen, bei denen das Bildformat Teil des Workflows ist.

### Vermeidung von Endlosschleifen

Der Effekt erkennt automatisch, wenn ein Typ versucht, sich selbst zu referenzieren, und verhindert Endlosschleifen.

## Hinweise

- **Vorsicht bei mehrfacher Formatkonvertierung**: Wenn mehrere Typen das Format konvertieren, kann es zu Qualitätsverlusten kommen.
- **Reihenfolge beachten**: Die Typen werden in der angegebenen Reihenfolge ausgeführt.
- **Ressourcenverbrauch**: Die Verkettung mehrerer komplexer Typen kann mehr Serverressourcen beanspruchen. Verwende den Cache des Media Managers, um die Belastung zu reduzieren.
- **Kompatibilität mit anderen AddOns**: Wenn du spezialisierte Media-AddOns wie Focuspoint verwendest, teste die Kompatibilität mit dem Chain-Effekt sorgfältig.

## Tipps

- Erstelle wiederverwendbare Typen für einzelne Schritte wie Formatierung, Größenanpassung usw.
- Verwende den Chain-Effekt, um komplexe Transformationen zu erstellen, ohne duplizierte Effekte
- Nutze diesen Effekt, um bestehende Typen zu kombinieren, ohne sie zu ändern
- Verwende aussagekräftige Namen für deine Media Manager Typen, um die Verkettung übersichtlich zu halten

## Fehlerbehebung

Falls Probleme auftreten:

1. Leere den Cache des Media Managers unter "Media Manager" > "Cache löschen"
2. Überprüfe, ob die angegebenen Typen existieren und korrekt geschrieben sind
3. Stelle sicher, dass keine zirkulären Abhängigkeiten entstehen
4. Überprüfe, ob alle Effekte in den verketteten Typen korrekt konfiguriert sind
5. Bei Format-spezifischen Problemen prüfe, ob dein PHP die entsprechenden Bildformate unterstützt

## Bekannte Probleme

- Bei sehr komplexen Verkettungen kann es zu Timeout-Problemen kommen
- Metadaten können zwischen den Verkettungsschritten verloren gehen

## Lizenz

MIT License

## Author

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO


## Credits

**Project Lead**

[Thomas Skerbis](https://github.com/skerbis)  

