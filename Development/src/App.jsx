import "./App.css";

function App() {
  return (
    <>
      <div className="flex justify-center">
        <div className="flex justify-center flex-col items-center bg-gradient-to-r from-cyan-600 bg-cyan-800 h-screen w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5">
          <img
            className="w-64"
            src="http://localhost:5173/src/assets/kappy.png"
            alt="asdas"
          />
          <div className="text-white font-semibold sans-serif text-center flex gap-20 flex-col from-cyan-200 bg-teal-600 p-6 rounded-lg w-80">
            <div className="text-2xl flex gap-2 flex-col">
              <h2>Seção 1: Hello World</h2>
              <h3>1/6 unidades</h3>
            </div>
            <div className="flex flex-col gap-3 font-bold text-2xl">
              <button className="bg-teal-500 hover:bg-blue-700 text-white py-3 px-4 border-blue-700 rounded-full w-full">
                A1 - Detalhes
              </button>
              <button className="bg-black hover:bg-blue-700 text-white py-3 px-4 border-blue-700 rounded-full w-full">
                Continuar
              </button>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

export default App;
