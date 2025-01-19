<header>
    <script src="https://cdn.tailwindcss.com"></script>
</header>
<section>
    <section>
        <div class="relative flex justify-center items-center min-h-screen p-0 overflow-hidden bg-center bg-cover">
            <div class="container z-1">
                <div class="flex flex-wrap -mx-3">
                    <div
                        class="flex justify-center flex-col w-full max-w-full px-3 mx-auto lg:mx-0 shrink-0 md:flex-0 md:w-7/12 lg:w-5/12 xl:w-4/12">
                        <div
                            class="relative flex justify-center flex-col min-w-0 break-words bg-transparent border-0 shadow-none lg:py4 rounded-2xl bg-clip-border">
                            <div class="p-6 pb-0 mb-0 flex justify-center">
                                <div>
                                    <h1 class="font-black text-9xl text-sky-500 drop-shadow-md">EROARE 403</h1>
                                    @if($exception->getMessage())
                                        <p class="text-lg font-medium text-gray-500 mt-10">{{$exception->getMessage()}}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="absolute top-0 right-0 flex-col justify-center hidden w-6/12 h-full max-w-full px-3 pr-0 my-auto text-center flex-0 lg:flex">
                        <div
                            class="relative flex flex-col justify-center h-full bg-cover px-24 overflow-hidden bg-[url('https://static.vecteezy.com/system/resources/previews/038/776/964/non_2x/ai-generated-of-marble-roman-greek-statue-against-transparent-background-free-png.png')]">
                                <span
                                    class="absolute top-0 left-0 w-full h-full bg-center bg-cover opacity-60"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</section>
