<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - MyBooks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/5.2.0/github-markdown.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    <style>
        * {
            box-sizing: border-box;
        }
        /* Wide tables scroll inside their own box on a phone (R11). */
        .markdown-body table {
            display: block;
            max-width: 100%;
            overflow-x: auto;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #f6f8fa;
        }
        
        .header {
            background: #102A43;
            color: white;
            padding: 20px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .header p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            font-size: 14px;
            opacity: 0.9;
            transition: opacity 0.2s;
        }
        
        .header-links a:hover {
            opacity: 1;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .markdown-body {
            background: white;
            padding: 45px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .markdown-body h1:first-child {
            border-bottom: 2px solid #1F4E79;
            padding-bottom: 15px;
        }
        
        .markdown-body h2 {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e1e4e8;
        }
        
        .markdown-body pre {
            background-color: #f6f8fa;
            border-radius: 6px;
            padding: 16px;
            overflow-x: auto;
        }
        
        .markdown-body code {
            background-color: #f6f8fa;
            border-radius: 3px;
            padding: 0.2em 0.4em;
            font-size: 85%;
        }
        
        .markdown-body pre code {
            background: none;
            padding: 0;
        }
        
        .markdown-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .markdown-body table th {
            background-color: #f6f8fa;
            font-weight: 600;
        }
        
        .markdown-body table th,
        .markdown-body table td {
            padding: 10px 15px;
            border: 1px solid #e1e4e8;
        }
        
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1F4E79;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: transform 0.2s, background 0.2s;
            display: none;
        }
        
        .back-to-top:hover {
            transform: scale(1.1);
            background: #183E61;
        }
        
        .back-to-top.visible {
            display: block;
        }

        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
            }
            
            .container {
                padding: 20px 10px;
            }
            
            .markdown-body {
                padding: 20px;
            }
            
            .header-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-nav">
            <div>
                <h1>📚 MyBooks API Documentation</h1>
                <p>RESTful API for mobile and third-party integrations</p>
            </div>
            <div class="header-links">
                <a href="{{ url('/') }}">Home</a>
                <a href="{{ url('/api/v1/health') }}" target="_blank">Health Check</a>
                <a href="https://github.com" target="_blank">GitHub</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <article class="markdown-body">
            {!! $content !!}
        </article>
    </div>
    
    <button class="back-to-top" id="back-to-top-btn" title="Back to top">
        ↑
    </button>
    
    <script nonce="{{ app('csp-nonce') }}" src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script nonce="{{ app('csp-nonce') }}">
        // Syntax highlighting
        document.querySelectorAll('pre code').forEach((block) => {
            hljs.highlightElement(block);
        });
        
        // Back to top button
        const backToTopBtn = document.getElementById('back-to-top-btn');
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });

        // Back to top button visibility
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });
    </script>
</body>
</html>
