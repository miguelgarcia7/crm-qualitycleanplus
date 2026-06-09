            <div class="main-navigation">
                <div class="container">
                    <div class="col-sm-12">
                        <nav class="navbar navbar-expand-lg navbar-dark">
                            <div class="container-fluid">
                                <a class="navbar-brand" href="/"><img src="/images/quality-cleaning-plus-logo.png" alt="Quality Cleaning Plus, Inc."></a>

                                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                                  <span class="navbar-toggler-icon"></span>
                                </button>
                                <div class="collapse navbar-collapse mt-2" id="navbarSupportedContent">
                                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                                        <li class="nav-item"><a href="/" class="nav-link {{ request()->is('/') ? 'active' : '' }}" title="Home">Home</a></li>
                                        <li class="nav-item"><a href="/about-us" class="nav-link {{ request()->is('about-us') ? 'active' : '' }}" title="About Us">About Us</a></li>
                                        <li class="nav-item"><a href="/services" class="nav-link {{ request()->is('services') ? 'active' : '' }}" title="Our Services">Services</a></li>
                                        <li class="nav-item"><a href="/job-openings" class="nav-link {{ request()->is('job-openings') ? 'active' : '' }}" title="Job Openings">Job Openings</a></li>
                                        <li class="nav-item"><a href="/contact-us" class="nav-link {{ request()->is('contact-us') ? 'active' : '' }}" title="Contact Us">Contact Us</a></li>
                                    </ul>

                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </div> {{-- /.main-navigation --}}
