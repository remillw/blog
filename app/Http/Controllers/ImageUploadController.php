<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    /**
     * Upload d'image de couverture temporaire
     */
    public function uploadCoverImage(Request $request)
    {
        \Log::info('ImageUploadController::uploadCoverImage - Request received:', [
            'has_file' => $request->hasFile('cover_image'),
            'files' => $request->allFiles(),
            'headers' => $request->headers->all(),
            'csrf_token' => $request->input('_token'),
            'method' => $request->method()
        ]);

        try {
            $request->validate([
                'cover_image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,svg|max:2048',
                'article_slug' => 'nullable|string'
            ], [
                'cover_image.required' => 'Veuillez sélectionner une image.',
                'cover_image.mimes' => 'L\'image doit être au format JPEG, PNG, GIF, WebP ou SVG.',
                'cover_image.max' => 'L\'image ne doit pas dépasser 2MB.',
                'cover_image.file' => 'Le fichier sélectionné n\'est pas valide.',
            ]);

            $file = $request->file('cover_image');
            $slug = $request->input('article_slug', 'temp-' . time());
            
            // Stocker le fichier temporairement
            $path = $this->storeCoverImage($file, $slug);
            $url = asset('storage/' . $path);

            \Log::info('Cover image uploaded successfully:', [
                'path' => $path,
                'url' => $url
            ]);

            return response()->json([
                'success' => true,
                'path' => $path,
                'url' => $url,
                'message' => 'Image uploadée avec succès'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed for cover image:', $e->errors());
            
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
                'message' => 'Erreur de validation'
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Upload failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store cover image with proper naming
     */
    private function storeCoverImage($file, $slug)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = $slug . '-cover.' . $extension;
        
        // Créer le chemin avec l'année/mois pour organiser les fichiers
        $directory = 'cover-images/' . date('Y/m');
        $path = $directory . '/' . $filename;
        
        // Vérifier si un fichier avec ce nom existe déjà et ajouter un suffixe si nécessaire
        $counter = 1;
        $originalPath = $path;
        while (Storage::disk('public')->exists($path)) {
            $pathInfo = pathinfo($originalPath);
            $path = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }
        
        // Stocker le fichier
        Storage::disk('public')->putFileAs($directory, $file, basename($path));
        
        return $path;
    }
}
