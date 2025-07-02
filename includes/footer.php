<footer class="bg-dark text-light py-5 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-blog me-2"></i><?php echo SITE_NAME; ?>
                </h5>
                <p class="text-light"><?php echo SITE_DESCRIPTION; ?></p>
                <div class="social-links">
                    <a href="#" class="text-light me-3"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-light me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-light me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-light me-3"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="<?php echo SITE_URL; ?>" class="text-light text-decoration-none">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/blog/search.php" class="text-light text-decoration-none">All
                            Blogs</a></li>
                    <li><a href="#" class="text-light text-decoration-none">About Us</a></li>
                    <li><a href="#" class="text-light text-decoration-none">Contact</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Categories</h6>
                <ul class="list-unstyled">
                    <?php
                    if (isset($categoryManager)) {
                        $footerCategories = array_slice($categoryManager->getCategories(), 0, 5);
                        foreach ($footerCategories as $category):
                    ?>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/?category=<?php echo $category['id']; ?>"
                            class="text-light text-decoration-none">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    </li>
                    <?php
                        endforeach;
                    }
                    ?>
                </ul>
            </div>

            <div class="col-lg-4 mb-4">
                <h6 class="fw-bold mb-3">Newsletter</h6>
                <p class="text-light">Subscribe to get the latest blog posts and news.</p>
                <form class="d-flex">
                    <input type="email" class="form-control me-2" placeholder="Your email" required>
                    <button class="btn btn-primary" type="submit" onclick="showThankYou(event)">Subscribe</button>
                    <div id="newsletter-popup" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title text-dark">Newsletter Subscription</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-dark">
                                    <p>Thank you for subscribing to our newsletter!</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    function showThankYou(e) {
                        e.preventDefault();
                        const email = e.target.previousElementSibling.value;
                        if (email) {
                            const modal = new bootstrap.Modal(document.getElementById('newsletter-popup'));
                            modal.show();
                            e.target.previousElementSibling.value = '';
                        }
                    }
                    </script>
                </form>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="text-muted mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="text-muted text-decoration-none me-3">Privacy Policy</a>
                <a href="#" class="text-muted text-decoration-none me-3">Terms of Service</a>
                <a href="<?php echo SITE_URL; ?>/admin/login.php" class="text-muted text-decoration-none">Admin</a>
            </div>
        </div>
    </div>
    <div class="text-center">
        Design and Developed by <a href="https://github.com/sumudu-k" target="_blank" class="text-light fw-bold">Sumudu
            Kulathunga</a>
        All rights reserved.
    </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

<?php if (isset($additionalJS)): ?>
<?php foreach ($additionalJS as $js): ?>
<script src="<?php echo $js; ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>

</html>