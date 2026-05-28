<script src="/assets/zxing/zxing-0.23.0.min.js"></script>

<style>
    #preview {
        width: 80%; /* Moins large que 100% */
        height: 80%;
        max-width: 400px; /* Limite la largeur maximale */
        max-height: 600px; /* Limite la hauteur maximale */
        margin: 20px auto;
        display: block;
        border: 2px solid #333;
        border-radius: 5px;
    }
</style>

{% include 'partials/search-form.tpl' %}

<div id="scan-feedback" class="alert alert-warning d-none" role="alert"></div>

<video id="preview" autoplay></video>

<script src="/assets/js/components/zxing.js"></script>
