
 <!DOCTYPE html>
 <html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 24px;
        }


        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 300px;
            padding: 8px 12px 8px 36px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #f8fafc;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: #FFCB32;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
 </head>
 <body>
    <!-- Header -->
     <div class="header">
    <div class="header-actions">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Rechercher ...">
        </div>
        <div class="header-user">
            <div class="user-avatar"><?php echo strtoupper(substr($current_user['name'], 0, 1)); ?></div>
        </div>
    </div>
</div>
 </body>
 </html>
