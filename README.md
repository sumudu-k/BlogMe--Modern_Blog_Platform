# 📝 <span style="color: #007ACC; font-weight: bold;">BlogMe - A Modern Blog Platform</span>

Welcome to **BlogMe**, a feature-rich blogging platform designed for sharing thoughts, managing content, and engaging with users. This platform includes both user and admin panels, offering a seamless experience for all stakeholders.

## <span style="color: #007ACC; font-weight: bold;">🌐 Live Demo</span>

[![Visit Live Website](https://img.shields.io/badge/Visit-Live%20Website-%23ee0000?style=for-the-badge&logo=globe&logoColor=white)](https://blogme.sumudu.site)

**🔗 Website:** https://blogme.sumudu.site

## ✨ <span style="color: #007ACC; font-weight: bold;">Features</span>

### 👤 <span style="color: #007ACC; font-weight: bold;">User Panel</span>

1. **User Registration and Login**

   - Secure user authentication with email and password.
   - Password hashing for enhanced security.

2. **Profile Management**

   - Update personal details such as name, email, and profile picture.

3. **Blog Creation and Management**

   - Create, edit, and delete personal blog posts.
   - Upload images to enhance blog content.

4. **Search and View Blogs**

   - Search for blogs by keywords or categories.
   - View featured blogs and explore trending content.

5. **Notifications**

   - Receive notifications for blog interactions and updates.

6. **Related Blogs**
   - View related blogs based on categories or tags for better content discovery.

### 🔧 <span style="color: #007ACC; font-weight: bold;">Admin Panel</span>

1. **Dashboard Overview**

   - View total counts of users, blogs, views, notifications and categories.

2. **User Management**

   - Block or delete user accounts.

3. **Blog Management**

   - View and delete blog posts.

4. **Category Management**

   - Create, update, or delete blog categories.
   - Add colors and descriptions to categories for better organization.

5. **Notifications Management**

   - Send and manage notifications for users.

6. **Demo Mode**

   - A restricted mode for showcasing the platform without making permanent changes.
   - Prevent modifications in demo mode to ensure data integrity during demonstrations.

7. **Blog Filters and Sorting**
   - Filter blogs by category, user, date or status.
   - Sort blogs by newest, oldest, title or most popular.

## 💻 <span style="color: #007ACC; font-weight: bold;">Technologies Used</span>

- **Frontend**: HTML, CSS, Bootstrap
- **Backend**: PHP
- **Database**: MySQL
- **Libraries**:
  - [PHPMailer](https://github.com/PHPMailer/PHPMailer) for email handling.
  - [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) for environment variable management.
- **Other Tools**:
  - Composer for dependency management.

## 🚀 <span style="color: #007ACC; font-weight: bold;">Installation</span>

1. Clone the repository:
   ```bash
   git clone https://github.com/sumudu-k/BlogMe.git
   ```
2. Install dependencies:
   ```bash
   composer install
   ```
3. Create a `.env` file by copying the `.env.example` file and update it with your database and site details:
   Then edit the `.env` file with your configuration.
4. Create a database called `articlewebsite` and import `articlewebsite.sql` database file to phpmyadmin.
5. Start your local server and access the platform.
6. You need to configure your server settings by adding your `email` and `app password` for working password reset functionality.

## 🤝 <span style="color: #007ACC; font-weight: bold;">Contributing</span>

We welcome contributions to improve BlogMe! To contribute:

1. Fork the repository.
2. Create a new branch for your feature or bug fix.
3. Submit a pull request with a detailed description of your changes.

## 📄 <span style="color: #007ACC; font-weight: bold;">License</span>

## This project is licensed under the MIT License.

## 🟡<span style="color: #007ACC; font-weight: bold;">Screenshots</span>

<p float="left">
  <img src="https://raw.githubusercontent.com/sumudu-k/BlogMe/refs/heads/development/SCREENSHOTS/user-homepage.png" width="48%" />
  <img src="https://raw.githubusercontent.com/sumudu-k/BlogMe/refs/heads/development/SCREENSHOTS/user-search.png" width="48%" />
</p>
<p float="left">
  <img src="https://raw.githubusercontent.com/sumudu-k/BlogMe/refs/heads/development/SCREENSHOTS/admin-dashboard.png" width="48%" />
  <img src="https://raw.githubusercontent.com/sumudu-k/BlogMe/refs/heads/development/SCREENSHOTS/user-posts.png" width="48%" />

</p>
Thank you for using BlogMe! If you have any questions or feedback, feel free to contact me.
