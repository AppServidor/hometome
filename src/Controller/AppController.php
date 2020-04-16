<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\UserRepository;
use App\Repository\MensajesRepository;
use App\Form\RegistrationFormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;
use App\Entity\Foto;
use App\Entity\Mensajes;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class AppController extends AbstractController
{
    /**
     * @Route("/home", name="home_user")
     */
    public function home_user(UserRepository $userRepository): Response
    {
        return $this->render('app/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    /**
     * @Route("/home/perfil", name="perfil_show", methods={"GET"})
     */
    public function show(): Response
    {
        $user = $this->getUser();
        $preferencias = $user->getPreferencias();
        $fotos = $user->getFoto();
        return $this->render('app/perfil/show.html.twig', [
            'user' => $user,
            'preferencias' => $preferencias,
            'fotos' => $fotos

        ]);
    }

    /**
     * @Route("/home/perfil/editar", name="perfil_user")
     */
    public function perfil_user(Request $request): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fotoFile =  $form->get('foto')->getData();
            if ($fotoFile != null){
                $this->getDoctrine()->getManager()->flush();

                self::renamePic($user,$fotoFile);
            } else {
             $this->getDoctrine()->getManager()->flush();
            }

            return $this->redirectToRoute('perfil_show');
        }

        return $this->render('app/perfil/perfil.html.twig', [
            'user' => $user,
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="perfil_delete", methods={"DELETE"})
     */
    public function delete(Request $request, User $user, TokenStorageInterface $tokenStorage, SessionInterface $session): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            try{
                $directory ='users/user'.$user->getId();
                if (file_exists ($directory)){
                    self::rrmdir($directory);
                }
                
            }catch(IOExceptionInterface $exception){
                echo "An error occurred while creating your directory at " . $exception->getPath();
            }

            $tokenStorage->setToken(null);
            $session->invalidate();
            
            $entityManager = $this->getDoctrine()->getManager();
            $fotos = $user->getFoto();
            foreach ($fotos as $foto){
                $entityManager->remove($foto);
            }
            $entityManager->remove($user);
            $entityManager->flush();

        }

        return $this->redirectToRoute('app_logout');
    }

    /**
     * @Route("/picUser/{id}", name="perfilPic_delete", methods={"DELETE"})
     */
    public function deletePicture(Request $request, Foto $foto): Response
    {
        $userId = $_POST['idUsuario'];

        $directorio = 'users/user' . $userId;
        $nombreFoto = $directorio.'/'.$foto->getNombre();
        $filesystem = new Filesystem();

        if ($this->isCsrfTokenValid('delete' . $foto->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($foto);
            $entityManager->flush();
            try {
                $filesystem->remove($nombreFoto);
                if (self::is_dir_empty($directorio)) {
                    rmdir($directorio);
                }
            } catch (IOExceptionInterface $exception) {
                echo "An error occurred while creating your directory at " . $exception->getPath();
            }
        }
        return $this->redirectToRoute('perfil_show');
    }

        /**
     * @Route("/home/chat/{id}", name="chat", methods={"GET"})
     */
    public function chat(Request $request, User $userChat, MensajesRepository $mensajeRepository): Response
    {
        $usuarioActivo= $this->getUser();

       $enviados= $mensajeRepository-> chatSender($usuarioActivo->getId(),$userChat->getId());
       $recibidos=$mensajeRepository-> chatSender($userChat->getId(),$usuarioActivo->getId());
        return $this->render('app/index.html.twig', [
            'enviados' => $enviados,
            'recibidos' => $recibidos,

        ]);
    }
        /**
     * @Route("/home/message", name="sendMessage", methods={"POST"})
     */
    public function sendMessage(Request $request, User $userChat): Response
    {
        $usuarioActivo= $this->getUser();
       $mensajeEnviado= $_POST['messagePost'];
       $hoy =getdate();
       $mensaje= new Mensajes();
       $mensaje->setSenderName($usuarioActivo->getId());
       $mensaje->setRecieverName($userChat->getId());
       $mensaje->setMessage($mensajeEnviado);
       $mensaje->setStatus(true);
       $mensaje->setDate($hoy);

       $entityManager = $this->getDoctrine()->getManager();
       $entityManager->persist($mensaje);
       $entityManager->flush();

        return $this->render('app/index.html.twig', [
            'enviados' => $enviados,
            'recibidos' => $recibidos,

        ]);
    }

    private function renamePic(User $user, $fotoFile) {
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        $foto = new Foto();
        $foto->setNombre($fotoFile->getClientOriginalName());
        $entityManager->persist($foto);
        $entityManager->flush();

        $idFoto = $foto->getId();
        $fileName ='img'.$user->getId().'-'.$idFoto.'.'.$fotoFile->guessExtension();

        $filesystem = new Filesystem();
        $filesystem->mkdir('users/user'.$user->getId());

        $fotoFile->move('users/user'.$user->getId(),$fileName);
        $foto->setNombre($fileName);
        $entityManager->flush();

        $user->addFoto($foto);
        $entityManager->flush();
    }

    function is_dir_empty($dir){
        if (!is_readable($dir)) return NULL;
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                return FALSE;
            }
        }
        return TRUE;
    }

    function rrmdir($src) {
        $dir = opendir($src);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $full = $src . '/' . $file;
                if ( is_dir($full) ) {
                    rrmdir($full);
                }
                else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }
}
