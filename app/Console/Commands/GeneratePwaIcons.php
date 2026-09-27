<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pwa:generate-icons {--force : Overwrite existing icons}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate PWA icons from the source SVG file';

    /**
     * Icon sizes to generate
     */
    protected $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $iconsPath = public_path('icons');
        $svgPath = $iconsPath . '/icon.svg';

        if (!file_exists($svgPath)) {
            $this->error('Source SVG file not found at: ' . $svgPath);
            return 1;
        }

        // Check if Imagick extension is available
        if (!extension_loaded('imagick')) {
            $this->warn('Imagick extension is not installed. Using fallback method.');
            $this->generateFallbackIcons();
            return 0;
        }

        $this->info('Generating PWA icons...');

        foreach ($this->sizes as $size) {
            $outputPath = $iconsPath . "/icon-{$size}x{$size}.png";

            if (file_exists($outputPath) && !$this->option('force')) {
                $this->line("  Skipping {$size}x{$size} (already exists)");
                continue;
            }

            try {
                $imagick = new \Imagick();
                $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                $imagick->readImage($svgPath);
                $imagick->setImageFormat('png32');
                $imagick->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1);
                $imagick->writeImage($outputPath);
                $imagick->destroy();

                $this->info("  Generated icon-{$size}x{$size}.png");
            } catch (\Exception $e) {
                $this->error("  Failed to generate {$size}x{$size}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('PWA icons generation complete!');
        $this->line('');
        $this->line('Note: If you see any errors, you can manually convert the SVG to PNG using:');
        $this->line('  - Online tools like https://cloudconvert.com/svg-to-png');
        $this->line('  - Adobe Illustrator or Inkscape');
        $this->line('  - Command line: inkscape -w SIZE -h SIZE icon.svg -o icon-SIZExSIZE.png');

        return 0;
    }

    /**
     * Generate placeholder icons when Imagick is not available
     */
    protected function generateFallbackIcons()
    {
        $iconsPath = public_path('icons');
        
        $this->info('Creating placeholder icons using GD library...');
        $this->line('For best results, please convert the SVG manually using an image editor.');
        $this->newLine();

        // Generate a simple placeholder icon using GD
        foreach ($this->sizes as $size) {
            $outputPath = $iconsPath . "/icon-{$size}x{$size}.png";

            if (file_exists($outputPath) && !$this->option('force')) {
                $this->line("  Skipping {$size}x{$size} (already exists)");
                continue;
            }

            // Create a simple colored square with text as placeholder
            $image = imagecreatetruecolor($size, $size);
            
            // Enable alpha blending
            imagealphablending($image, true);
            imagesavealpha($image, true);
            
            // Colors
            $indigo = imagecolorallocate($image, 79, 70, 229); // #4f46e5
            $white = imagecolorallocate($image, 255, 255, 255);
            
            // Fill with indigo
            imagefill($image, 0, 0, $indigo);
            
            // Add rounded corners effect (simplified)
            $radius = (int)($size * 0.18);
            
            // Draw "M" text
            $fontSize = (int)($size * 0.5);
            $fontPath = 5; // Built-in font
            
            // Center the letter
            $textWidth = imagefontwidth($fontPath) * strlen('M');
            $textHeight = imagefontheight($fontPath);
            $x = ($size - $textWidth) / 2;
            $y = ($size - $textHeight) / 2;
            
            // For larger sizes, use a bigger approach
            if ($size >= 96) {
                // Draw a simple "M" using GD's built-in fonts
                imagestring($image, 5, (int)($size * 0.35), (int)($size * 0.35), 'M', $white);
            }
            
            // Save the image
            imagepng($image, $outputPath);
            imagedestroy($image);
            
            $this->info("  Generated placeholder icon-{$size}x{$size}.png");
        }

        $this->newLine();
        $this->warn('Placeholder icons have been created.');
        $this->line('For production, please replace these with properly converted icons from the SVG.');
    }
}
